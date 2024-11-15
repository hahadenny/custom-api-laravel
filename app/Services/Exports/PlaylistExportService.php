<?php

namespace App\Services\Exports;

use App\Models\Playlist;
use App\Models\Project;
use App\Models\Template;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection as SupportCollection;

class PlaylistExportService
{
    const TYPE = 'playlist';

    protected ?TemplateExportService $templateExportService = null;

    public function batchExport(Project $project, array $params = []): SupportCollection
    {
        $this->templateExportService = new TemplateExportService();
        $paramsIds = array_values(array_filter(array_unique($params['ids'] ?? [])));
        $paramsGroupIds = array_values(array_filter(array_unique($params['group_ids'] ?? [])));

        $groups = $this->getGroups($project, $paramsIds, $paramsGroupIds);
        $playlists = $this->getPlaylists($project, $paramsIds);

        return collect([
            '_type' => self::TYPE,
            'playlistGroups' => $this->prepareGroups($groups),
            'playlists' => $this->preparePlaylists($playlists),
        ]);
    }

    public function generateFileName(Project $project): string
    {
        $fileName = preg_replace('/\W+/', '-', $project->company->name);
        $fileName .= '_'.preg_replace('/\W+/', '-', $project->name);
        $fileName .= '_playlists_'.now()->format('Y-m-d_H-i-s').'.json';

        return $fileName;
    }

    protected function getPlaylists(Project $project, array $paramsIds): Collection
    {
        $whereTemplateQuery = $this->getWhereTemplateQuery($project);

        return $project->listingPlaylists()
            ->with([
                'parentListingPivot',
                'pages' => function (MorphToMany $morphToMany) use ($whereTemplateQuery) {
                    $morphToMany
                        ->wherePivotNull('group_id')
                        ->where($whereTemplateQuery);
                },
                'pages.playlistListingPivots' => function (MorphMany $morphMany) use ($paramsIds) {
                    $morphMany->whereIn('playlist_id', $paramsIds);
                },
                'pageGroups',
                'pageGroups.parentListingPivot',
                'pageGroups.items' => function (MorphToMany $morphToMany) use ($whereTemplateQuery) {
                    $morphToMany->where($whereTemplateQuery);
                },
                'pageGroups.items.playlistListingPivots' => function (MorphMany $morphMany) use ($paramsIds) {
                    $morphMany->whereIn('playlist_id', $paramsIds);
                },
                'pageGroups.items.template',
                'pages.template',
            ])
            ->wherePivotNull('group_id')
            ->findMany($paramsIds);
    }

    protected function getGroups(Project $project, array $paramsIds, array $paramsGroupIds): Collection
    {
        $groupIds = $project->playlists()->whereKey($paramsIds)->pluck('playlist_group_id')
            ->merge($paramsGroupIds)->unique()->values();

        foreach ($groupIds as $groupId) {
            /** @var \Kalnoy\Nestedset\QueryBuilder|\Illuminate\Database\Eloquent\Relations\HasMany $query */
            $query = $project->playlistGroups();
            $groupIds = $query->whereAncestorOf($groupId)->pluck('id')
                ->merge($groupIds)->unique()->values();
        }

        $playlistIds = collect($paramsIds);

        foreach ($this->getGroupIdsNoSelectionIn($project, $paramsIds, $paramsGroupIds) as $noSelectionInGroupId) {
            /** @var \Kalnoy\Nestedset\QueryBuilder|\Illuminate\Database\Eloquent\Relations\HasMany $query */
            $query = $project->playlistGroups();
            $noSelectionInGroups = $query->whereDescendantOrSelf($noSelectionInGroupId)->with(['items'])->get();

            $groupIds = $noSelectionInGroups->pluck('id')
                ->merge($groupIds)->unique()->values();
            $playlistIds = $noSelectionInGroups->pluck('items.*.id')->flatten()
                ->merge($playlistIds)->unique()->values();
        }

        $whereTemplateQuery = $this->getWhereTemplateQuery($project);

        return $project->playlistGroups()
            ->with([
                'parentListingPivot',
                'items' => function (HasMany $hasMany) use ($playlistIds) {
                    $hasMany->whereKey($playlistIds);
                },
                'items.parentListingPivot',
                'items.pages' => function (MorphToMany $morphToMany) use ($whereTemplateQuery) {
                    $morphToMany
                        ->wherePivotNull('group_id')
                        ->where($whereTemplateQuery);
                },
                'items.pages.playlistListingPivots' => function (MorphMany $morphMany) use ($playlistIds) {
                    $morphMany->whereIn('playlist_id', $playlistIds);
                },
                'items.pageGroups',
                'items.pageGroups.parentListingPivot',
                'items.pageGroups.items' => function (MorphToMany $morphToMany) use ($whereTemplateQuery) {
                    $morphToMany->where($whereTemplateQuery);
                },
                'items.pageGroups.items.playlistListingPivots' => function (MorphMany $morphMany) use ($playlistIds) {
                    $morphMany->whereIn('playlist_id', $playlistIds);
                },
                'items.pageGroups.items.template',
                'items.pages.template',
            ])
            ->findMany($groupIds);
    }

    protected function getWhereTemplateQuery(Project $project): callable
    {
        $templateIdQuery = $project->company->templates()
            ->select(['id']);
//            ->where(function (Builder $q) {
//                $q
//                    ->whereNull('preset')
//                    ->orWhereNot('preset', Template::PRESET_D3);
//            });

        return function (Builder $q) use ($templateIdQuery) {
            $q
                ->whereNull('template_id')
                ->orWhereIn('template_id', $templateIdQuery);
        };
    }

    protected function getGroupIdsNoSelectionIn(Project $project, array $paramsIds, array $paramsGroupIds): array
    {
        $noSelectionInGroupIds = [];

        /** @var \Kalnoy\Nestedset\QueryBuilder|\Illuminate\Database\Eloquent\Relations\HasMany $selectionInGroupQuery */
        $selectionInGroupQuery = $project->playlistGroups();
        $selectionInGroupQuery
            ->where(function (Builder $q) use ($project, $paramsIds, $paramsGroupIds) {
                $q
                    ->whereIn('id', $project->playlistGroups()
                        ->select(['parent_id'])
                        ->whereKey($paramsGroupIds)
                    )
                    ->orWhereIn('id', $project->playlists()
                        ->select(['playlist_group_id'])
                        ->whereKey($paramsIds)
                    );
            });

        foreach ($paramsGroupIds as $paramsGroupId) {
            if ($selectionInGroupQuery->clone()->whereDescendantOrSelf($paramsGroupId)->doesntExist()) {
                $noSelectionInGroupIds[] = $paramsGroupId;
            }
        }

        return $noSelectionInGroupIds;
    }

    protected function preparePlaylists(Collection $playlists): SupportCollection
    {
        $results = collect();

        foreach ($playlists as $playlist) {
            /** @var \App\Models\Playlist $playlist */

            $preparedPlaylist = $playlist->only([
                'id',
                'company_id',
                'project_id',
                'name',
                'type',
                'is_active',
                'sort_order',
                'pages',
                'pageGroups',
            ]);

            $preparedPlaylist['pages'] = $this->preparePages($playlist->pages, $playlist);
            $preparedPlaylist['pageGroups'] = $this->preparePageGroups($playlist->pageGroups, $playlist);

            $results->add($preparedPlaylist);
        }

        return $results;
    }

    protected function prepareGroups(Collection $groups): SupportCollection
    {
        $results = collect();

        foreach ($groups as $group) {
            /** @var \App\Models\PlaylistGroup $group */

            $preparedGroup = $group->only([
                'id',
                'name',
                '_lft',
                '_rgt',
                'parent_id',
                'sort_order',
            ]);

            $preparedGroup['playlists'] = $this->preparePlaylists($group->items);

            $results->add($preparedGroup);
        }

        return $results;
    }

    protected function preparePages(Collection $pages, Playlist $playlist): SupportCollection
    {
        $results = collect();

        foreach ($pages as $page) {
            /** @var \App\Models\Page $page */

            $page->setParentModel($playlist);

            $preparedPage = $page->only([
                'id',
                'name',
                'description',
                'has_media',
                'page_number',
                'color',
                'data',
                'sort_order',
                'template',
            ]);

            if (! is_null($page->template)) {
                $preparedPage['template'] = $this->templateExportService
                    ->prepareTemplates(new Collection([$page->template]))
                    ->first();
            }

            $results->add($preparedPage);
        }

        return $results;
    }

    protected function preparePageGroups(Collection $pageGroups, Playlist $playlist): SupportCollection
    {
        $results = collect();

        foreach ($pageGroups as $pageGroup) {
            /** @var \App\Models\PageGroup $pageGroup */

            $preparedPageGroup = $pageGroup->only([
                'id',
                'name',
                'color',
                '_lft',
                '_rgt',
                'parent_id',
                'sort_order',
            ]);

            $preparedPageGroup['pages'] = $this->preparePages($pageGroup->items, $playlist);

            $results->add($preparedPageGroup);
        }

        return $results;
    }
}
