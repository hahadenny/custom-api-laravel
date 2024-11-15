<?php

namespace App\Services\Imports;

use App\Models\Template;
use App\Models\TemplateGroup;
use App\Models\User;
use App\Traits\Services\Imports\DataTree;
use App\Traits\Services\UniqueNameTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use JsonMachine\Items;

class TemplateImportService
{
    use DataTree, UniqueNameTrait;

    /**
     * @var \App\Models\Template[]
     */
    protected array $oldIdNewTemplates = [];

    /**
     * @var \App\Models\TemplateGroup[]
     */
    protected array $oldIdNewGroups = [];

    protected ?User $user = null;

    protected array $params = [];

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): TemplateImportService
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

    public function getListingFromFile(string $file, $useData = false): array
    {
        $types = Items::fromFile($file, ['pointer' => '/_type']);
        $type = iterator_to_array($types)['_type'];

        $normalizedGroups = [];
        $items = Items::fromFile($file, ['pointer' => '/templateGroups']);
        foreach ($items as $templateGroup) {
            $templateGroup = (array) $templateGroup;

            foreach ($templateGroup['templates'] as &$template) {
                $template = (array) $template;
                if (!$useData) {
                    unset($template['data']);
                }

            }
            $templateGroup['items'] = $templateGroup['templates'];
            unset($templateGroup['templates']);
            $normalizedGroups[] = $templateGroup;
        }

        $normalizedItems = [];
        $items = Items::fromFile($file, ['pointer' => '/templates']);
        foreach ($items as $item) {
            $template = (array) $item;
            if (!$useData) {
                unset($template['data']);
            }
            $normalizedItems[] = $template;
        }

        return array_merge(
            ['_type' => $type],
            static::toTreeOfComponents($this->buildTree([
                'children' => $normalizedGroups,
                'items' => $normalizedItems,
            ]))
        );
    }

    public function import(User $authUser, array $params = []): void
    {
        $this->setUser($authUser);

        $data = $this->getListingFromFile($params['file_path'], true);

        $selectedData = $this->determineSelectedData(static::separateComponentsInTree($data)['children'], $params);

        $this->params = array_merge($params, $selectedData);

        DB::transaction(function () use ($data) {
            $this->importComponents($data['components']);
        });
    }

    public function updateSortOrderOfList(): void
    {
        if (! empty($this->oldIdNewTemplates)) {
            array_values($this->oldIdNewTemplates)[0]->parentListingPivot->updateSortOrderOfList();
        } elseif (! empty($this->oldIdNewGroups)) {
            array_values($this->oldIdNewGroups)[0]->parentListingPivot->updateSortOrderOfList();
        }
    }

    protected function normalizeData(array $data): array
    {
        $normalizedGroups = [];

        foreach ($data['templateGroups'] as $templateGroup) {
            $templateGroup['items'] = $templateGroup['templates'];
            unset($templateGroup['templates']);

            $normalizedGroups[] = $templateGroup;
        }

        $normalizedData = $data;

        $normalizedData['children'] = $normalizedGroups;
        $normalizedData['items'] = $normalizedData['templates'] ?? [];

        unset($normalizedData['templateGroups']);
        unset($normalizedData['templates']);

        return $normalizedData;
    }

    protected static function filterTreeRecursive(array $tree): array
    {
//        $tree['components'] = array_values(array_filter($tree['components'], function ($component) {
//            return isset($component['components']) || ($component['preset'] ?? null) !== Template::PRESET_D3;
//        }));

        foreach ($tree['components'] as &$component) {
            if (isset($component['components'])) {
                $component = static::filterTreeRecursive($component);
            }
        }
        unset($component);

        return $tree;
    }

    protected function importComponents(array $components): void
    {
        $this->importComponentsRecursive($components, null);
        $this->updateSortOrderOfList();
    }

    protected function importComponentsRecursive(array $components, ?TemplateGroup $parent): void
    {
        foreach (collect($components)->sortBy(['sort_order', 'id'])->values()->all() as $component) {
            if (isset($component['components'])) {
                $group = $this->importGroup($component, $parent);
                $this->importComponentsRecursive($component['components'], $group);
            } else {
                $this->importTemplate($component, $parent);
            }
        }
    }

    public function importTemplate(array $data, ?TemplateGroup $group = null): ?Template
    {
        if (! empty($this->params['ids']) || ! empty($this->params['group_ids'])) {
            if (empty($this->params['ids']) || ! in_array($data['id'], $this->params['ids'], true)) {
                return null;
            }
        }
        if (isset($this->oldIdNewTemplates[$data['id']])) {
            return $this->oldIdNewTemplates[$data['id']];
        }

        $template = new Template();

        $uniqueNameQuery = is_null($group) ? $this->user->company->templates() : $group->templates();

        $template->preset = $data['preset'];
        $template->name = $this->replicateUniqueName($uniqueNameQuery, $data['name']);
        $template->type = $data['type'];
        $template->engine = $data['engine'] ?? null;
        $template->color = $data['color'] ?? null;
        $template->data = $data['data'] ?? '';

        $template->createdBy()->associate($this->user);
        $template->company()->associate($this->user->company);
        $template->templateGroup()->associate($group);

        $template->save();

        /** @var \App\Models\CompanyTemplateListing $listingPivot */
        $listingPivot = $template->parentListingPivot()->make([
            'company_id' => $template->company_id,
        ]);
        $listingPivot->group()->associate($group);
        $listingPivot->saveOrRestore();

        $this->oldIdNewTemplates[$data['id']] = $template;

        return $template;
    }

    protected function importGroup(array $data, ?TemplateGroup $parent = null): ?TemplateGroup
    {
        if (! empty($this->params['ids']) || ! empty($this->params['group_ids'])) {
            if (empty($this->params['group_ids']) || ! in_array($data['id'], $this->params['group_ids'], true)) {
                return null;
            }
        }
        if (isset($this->oldIdNewGroups[$data['id']])) {
            return $this->oldIdNewGroups[$data['id']];
        }

        $group = new TemplateGroup();

        $uniqueNameQuery = is_null($parent) ? $this->user->company->templateGroups() : $parent->children();

        $group->name = $this->replicateUniqueName($uniqueNameQuery, $data['name']);
        $group->createdBy()->associate($this->user);
        $group->company()->associate($this->user->company);
        $group->parent()->associate($parent);
        $group->save();

        /** @var \App\Models\CompanyTemplateListing $listingPivot */
        $listingPivot = $group->parentListingPivot()->make([
            'company_id' => $this->user->company->id,
        ]);
        $listingPivot->group()->associate($parent);
        $listingPivot->saveOrRestore();

        $this->oldIdNewGroups[$data['id']] = $group;

        return $group;
    }
}
