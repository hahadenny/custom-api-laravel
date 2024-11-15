<?php

namespace App\Services\Imports;

use App\Models\Page;
use App\Models\PageGroup;
use App\Models\Playlist;
use App\Models\PlaylistGroup;
use App\Models\Project;
use App\Models\Template;
use App\Models\User;
use App\Traits\Services\Imports\DataTree;
use App\Traits\Services\UniqueNameTrait;
use Illuminate\Support\Facades\DB;

class PlaylistImportService
{
    use DataTree, UniqueNameTrait;

    /**
     * @var \App\Models\Playlist[]
     */
    protected array $oldIdNewPlaylists = [];

    /**
     * @var \App\Models\PlaylistGroup[]
     */
    protected array $oldIdNewGroups = [];

    protected ?User $user = null;

    protected ?TemplateImportService $templateImportService = null;

    protected array $params = [];

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): PlaylistImportService
    {
        $this->user = $user;
        return $this;
    }

    public function listing(array $data): array
    {
        $normalizedData = $this->normalizeData($data);

        return array_merge(
            ['_type' => $data['_type']],
            static::toTreeOfComponents($this->buildTree([
                'children' => $normalizedData['children'],
                'items' => $normalizedData['items'],
            ]))
        );
    }

    public function import(User $authUser, Project $project, array $params = []): void
    {
        $this->setUser($authUser);

        $data = json_decode($params['data'], true);
        $data = static::filterTreeRecursive($data);
        $selectedData = $this->determineSelectedData(static::separateComponentsInTree($data)['children'], $params);

        $this->params = array_merge($params, $selectedData);

        $this->templateImportService = new TemplateImportService();
        $this->templateImportService->setUser($authUser);

        DB::transaction(function () use ($data, $project) {
            $this->importComponents($data['components'], $project);
        });
    }

    public function updateSortOrderOfList(): void
    {
        if (! empty($this->oldIdNewPlaylists)) {
            array_values($this->oldIdNewPlaylists)[0]->parentListingPivot->updateSortOrderOfList();
        } elseif (! empty($this->oldIdNewGroups)) {
            array_values($this->oldIdNewGroups)[0]->parentListingPivot->updateSortOrderOfList();
        }
    }

    protected function normalizeData(array $data): array
    {
        $normalizedGroups = [];

        foreach ($data['playlistGroups'] as $playlistGroup) {
            $playlistGroup['items'] = $playlistGroup['playlists'];
            unset($playlistGroup['playlists']);

            $normalizedGroups[] = $playlistGroup;
        }

        $normalizedData = $data;

        $normalizedData['children'] = $normalizedGroups;
        $normalizedData['items'] = $normalizedData['playlists'] ?? [];

        unset($normalizedData['playlistGroups']);
        unset($normalizedData['playlists']);

        return $normalizedData;
    }

    protected static function filterTreeRecursive(array $tree): array
    {
        foreach ($tree['components'] as &$component) {
            if (isset($component['components'])) {
                $component = static::filterTreeRecursive($component);
            } else {
//                $component['pages'] = array_values(array_filter($component['pages'], function ($page) {
//                    return ($page['template']['preset'] ?? null) !== Template::PRESET_D3;
//                }));

                foreach ($component['pageGroups'] as &$pageGroup) {
//                    $pageGroup['pages'] = array_values(array_filter($pageGroup['pages'], function ($page) {
//                        return ($page['template']['preset'] ?? null) !== Template::PRESET_D3;
//                    }));
                }
                unset($pageGroup);
            }
        }
        unset($component);

        return $tree;
    }

    protected function importComponents(array $components, Project $project): void
    {
        $this->importComponentsRecursive($components, $project, null);

        $this->updateSortOrderOfList();
        $this->templateImportService->updateSortOrderOfList();
    }

    protected function importComponentsRecursive(array $components, Project $project, ?PlaylistGroup $parent): void
    {
        foreach (collect($components)->sortBy(['sort_order', 'id'])->values()->all() as $component) {
            if (isset($component['components'])) {
                $group = $this->importGroup($component, $project, $parent);
                $this->importComponentsRecursive($component['components'], $project, $group);
            } else {
                $this->importPlaylist($component, $project, $parent);
            }
        }
    }

    protected function importPlaylist(array $data, Project $project, ?PlaylistGroup $group): ?Playlist
    {
        if (! empty($this->params['ids']) || ! empty($this->params['group_ids'])) {
            if (empty($this->params['ids']) || ! in_array($data['id'], $this->params['ids'], true)) {
                return null;
            }
        }
        if (isset($this->oldIdNewPlaylists[$data['id']])) {
            return $this->oldIdNewPlaylists[$data['id']];
        }

        $playlist = new Playlist();

        $uniqueNameQuery = is_null($group) ? $project->playlists() : $group->playlists();

        $playlist->name = $this->replicateUniqueName($uniqueNameQuery, $data['name']);
        $playlist->type = $data['type'];
        $playlist->is_active = $data['is_active'];

        $playlist->createdBy()->associate($this->user);
        $playlist->project()->associate($project);
        $playlist->company()->associate($this->user->company);
        $playlist->playlistGroup()->associate($group);

        $playlist->save();

        /** @var \App\Models\ProjectListing $listingPivot */
        $listingPivot = $playlist->parentListingPivot()->make([
            'project_id' => $project->id,
        ]);
        $listingPivot->group()->associate($group);
        $listingPivot->saveOrRestore();

        $oldIdNewPages = $this->importPages($data['pages'], $playlist);
        $oldIdNewPageGroups = $this->importPageGroups($data['pageGroups'], $playlist);

        if (! empty($oldIdNewPages)) {
            array_values($oldIdNewPages)[0]->parentListingPivot->updateSortOrderOfList();
        } elseif (! empty($oldIdNewPageGroups)) {
            array_values($oldIdNewPageGroups)[0]->parentListingPivot->updateSortOrderOfList();
        }

        $this->oldIdNewPlaylists[$data['id']] = $playlist;

        return $playlist;
    }

    protected function importGroup(array $data, Project $project, ?PlaylistGroup $parent = null): ?PlaylistGroup
    {
        if (! empty($this->params['ids']) || ! empty($this->params['group_ids'])) {
            if (empty($this->params['group_ids']) || ! in_array($data['id'], $this->params['group_ids'], true)) {
                return null;
            }
        }
        if (isset($this->oldIdNewGroups[$data['id']])) {
            return $this->oldIdNewGroups[$data['id']];
        }

        $group = new PlaylistGroup();

        $uniqueNameQuery = is_null($parent) ? $project->playlistGroups() : $parent->children();

        $group->name = $this->replicateUniqueName($uniqueNameQuery, $data['name']);
        $group->createdBy()->associate($this->user);
        $group->project()->associate($project);
        $group->company()->associate($this->user->company);
        $group->parent()->associate($parent);
        $group->save();

        /** @var \App\Models\ProjectListing $listingPivot */
        $listingPivot = $group->parentListingPivot()->make([
            'project_id' => $project->id,
        ]);
        $listingPivot->group()->associate($parent);
        $listingPivot->saveOrRestore();

        $this->oldIdNewGroups[$data['id']] = $group;

        return $group;
    }

    protected function importPageGroups(array $data, Playlist $playlist): array
    {
        /** @var \App\Models\PageGroup[] $oldIdNewPageGroups */
        $oldIdNewPageGroups = [];
        $data = collect($data)->sortBy(['_lft', 'sort_order', 'id'])->values()->all();

        foreach ($data as $item) {
            if (isset($oldIdNewPageGroups[$item['id']])) {
                continue;
            }

            /** @var \App\Models\PageGroup $parent */
            $parent = is_null($item['parent_id']) ? null : $oldIdNewPageGroups[$item['parent_id']];

            $pageGroup = new PageGroup();

            $uniqueNameQuery = is_null($parent) ? $playlist->pageGroups() : $parent->children();

            $pageGroup->name = $this->replicateUniqueName($uniqueNameQuery, $item['name']);
            $pageGroup->color = $item['color'] ?? null;
            $pageGroup->playlist()->associate($playlist);
            $pageGroup->company()->associate($this->user->company);
            $pageGroup->parent()->associate($parent);
            $pageGroup->save();

            /** @var \App\Models\PlaylistListing $listingPivot */
            $listingPivot = $pageGroup->parentListingPivot()->make([
                'playlist_id' => $playlist->id,
            ]);
            $listingPivot->company()->associate($this->user->company);
            $listingPivot->group()->associate($parent);
            $listingPivot->saveOrRestore();

            $this->importPages($item['pages'], $playlist, $pageGroup);

            $oldIdNewPageGroups[$item['id']] = $pageGroup;
        }

        return $oldIdNewPageGroups;
    }

    protected function importPages(array $data, Playlist $playlist, PageGroup|int|null $group = null): array
    {
        $data = collect($data)->sortBy(['sort_order', 'id'])->values()->all();
        /** @var \App\Models\Page[] $oldIdNewPages */
        $oldIdNewPages = [];

        foreach ($data as $item) {
            if (isset($oldIdNewPages[$item['id']])) {
                continue;
            }

            $page = new Page();

            $page->name = $item['name'];
            $page->description = $item['description'];
            $page->has_media = $item['has_media'];
            $page->page_number = $item['page_number'];
            $page->color = $item['color'] ?? null;
            $page->data = $item['data'];

            if (isset($item['template']['id'])) {
                $template = $this->templateImportService->importTemplate($item['template']);
                $page->template()->associate($template);
            }

            $defaultChannel = $this->user->company->channels()->defaultOfType($page->template?->engine)->first();

            $page->createdBy()->associate($this->user);
            $page->company()->associate($this->user->company);
            $page->channel()->associate($defaultChannel);
            $page->channelEntity()->associate($defaultChannel);
            $page->setParentModel($playlist);

            $page->save();

            /** @var \App\Models\PlaylistListing $listingPivot */
            $listingPivot = $page->playlistListingPivots()->make([
                'playlist_id' => $playlist->id,
            ]);
            $listingPivot->company()->associate($this->user->company);
            $listingPivot->group()->associate($group);
            $listingPivot->saveOrRestore();

            $page->unsetRelation('playlistListingPivots');

            $oldIdNewPages[$item['id']] = $page;
        }

        return $oldIdNewPages;
    }
}
