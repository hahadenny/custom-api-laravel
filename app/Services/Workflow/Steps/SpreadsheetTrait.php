<?php

namespace App\Services\Workflow\Steps;

use App\Enums\ChannelEntityType;
use App\Enums\WorkflowType;
use App\Models\Page;
use App\Models\Playlist;
use App\Models\Template;
use App\Services\Workflow\StepRequest;
use App\Services\Workflow\StepResponse;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

trait SpreadsheetTrait
{
    use SetDataTrait;

    abstract protected function getSheetValues(array $data): Collection;

    public function handle(StepRequest $request): StepResponse
    {
        $params = $request->getParam('global')['params'];
        $data = $this->validateParamData($request->getParam('data', []));

        $params = array_merge($params, $this->retrieveItemData($request->getParam('data', [])));

        $prevResponseParams = $request->getParam('prev_response_params');
        $models = $prevResponseParams['models'] ?? new EloquentCollection();

        $items = $this->makeItems($this->getSheetValues($data), $params);
        $this->updateModels($models, $items, $request);

        return new StepResponse(['models' => $models]);
    }

    protected function retrieveItemData(array $data): array
    {
        return match ($this->workflow->type) {
            WorkflowType::Page => $this->retrievePageData($data),
            default => [],
        };
    }

    protected function retrievePageData(array $data): array
    {
        return collect($data)->only([
            'template_id',
        ])
            ->all();
    }

    protected function makeItems(Collection $values, array $params): array
    {
        return match ($this->workflow->type) {
            WorkflowType::Page => $this->makePageItems($values, $params),
        };
    }

    protected function makePageItems(Collection $values, array $params): array
    {
        $pageItems = [];
        $company = $this->workflow->company;

        foreach ($values as $index => $value) {
            /** @var \Illuminate\Support\Collection $value */
            $pageItem = [];
            $rawPageData = new Collection($value->toArray());
            $normalizedNames = $this->getNormalizedNameToNames($value->keys()->all());

            if (array_key_exists('playlist_id', $params)) {
                $pageItem['playlist_id'] = $params['playlist_id'];
            }
            if (array_key_exists('template_id', $params)) {
                $pageItem['template_id'] = $params['template_id'];
            }

            if (isset($normalizedNames['page_group_id'])) {
                if ((int) ($rawPageData[$normalizedNames['page_group_id']] ?? 0) > 0) {
                    $pageItem['page_group_id'] = (int) $rawPageData[$normalizedNames['page_group_id']];
                }
                unset($rawPageData[$normalizedNames['page_group_id']]);
            }

            if (isset($normalizedNames['template name'])) {
                if (trim($rawPageData[$normalizedNames['template name']] ?? '') !== '') {
                    /** @var \App\Models\Template|null $template */
                    $template = $company->templates()
                        ->where('name', $rawPageData[$normalizedNames['template name']])
                        ->orderBy('id')
                        ->first();

                    if (is_null($template)) {
                        throw ValidationException::withMessages([
                            'file' => 'The template "' . $normalizedNames['template name'] . '" is not found.'
                        ]);
                    }

                    $pageItem['template_id'] = $template->id;
                }
                unset($rawPageData[$normalizedNames['template name']]);
            }

            if (isset($normalizedNames['channel name'])) {
                if (trim($rawPageData[$normalizedNames['channel name']] ?? '') !== '') {
                    /** @var \App\Models\Channel|null $channel */
                    $channel = $company->channels()
                        ->where('name', $rawPageData[$normalizedNames['channel name']])
                        ->orderBy('id')
                        ->first();

                    if (is_null($channel)) {
                        throw ValidationException::withMessages([
                            'file' => 'The channel "' . $normalizedNames['channel name'] . '" is not found.'
                        ]);
                    }

                    $pageItem['channel_id'] = $channel->id;
                    $pageItem['channel_entity_type'] = ChannelEntityType::Channel->value;
                    $pageItem['channel_entity_id'] = $channel->id;
                }
                unset($rawPageData[$normalizedNames['channel name']]);
            }

            Validator::validate(
                $rawPageData->toArray(),
                [$normalizedNames['page name'] ?? 'page name' => 'required'],
                [
                    'required' => trans('messages.validation.spreadsheet_row_number', ['number' => $index + 1])
                        . ' ' . trans('validation.required')
                ]
            );

            if (isset($normalizedNames['page name'])) {
                if (trim($rawPageData[$normalizedNames['page name']] ?? '') !== '') {
                    $pageItem['name'] = trim($rawPageData[$normalizedNames['page name']]);
                }
                unset($rawPageData[$normalizedNames['page name']]);
            }

            if (isset($normalizedNames['description'])) {
                if (trim($rawPageData[$normalizedNames['description']] ?? '') !== '') {
                    $pageItem['description'] = trim($rawPageData[$normalizedNames['description']]);
                }
                unset($rawPageData[$normalizedNames['description']]);
            }

            if (isset($normalizedNames['is live'])) {
                if (trim($rawPageData[$normalizedNames['is live']] ?? '') !== '') {
                    $pageItem['is_live'] = (bool) trim($rawPageData[$normalizedNames['is live']]);
                }
                unset($rawPageData[$normalizedNames['is live']]);
            }

            if (isset($normalizedNames['page number'])) {
                if (trim($rawPageData[$normalizedNames['page number']] ?? '') !== '') {
                    $pageItem['page_number'] = (int) $rawPageData[$normalizedNames['page number']];
                }
                unset($rawPageData[$normalizedNames['page number']]);
            }

            $requiredMessages = [trans(
                'validation.required',
                ['attribute' => $normalizedNames['template name'] ?? 'template']
            )];

            if (isset($normalizedNames['template name'])) {
                $requiredMessages = array_merge(
                    [trans('messages.validation.spreadsheet_row_number', ['number' => $index + 1])],
                    $requiredMessages
                );
            }

            Validator::validate(
                $pageItem,
                ['template_id' => 'required'],
                ['required' => implode(' ', $requiredMessages)]
            );

            /** @var \App\Models\Template $template */
            $template = Template::query()->find($pageItem['template_id']);

            if (is_null($template)) {
                throw ValidationException::withMessages([
                    $normalizedNames['template name'] ?? 'template_id' => 'The template is not found.',
                ]);
            }

            $rawPageData = $rawPageData->filter(fn ($rawPageDatum) => trim($rawPageDatum) != '');
            $pageData = [];

            $template->propsFromSchema($template->data ?? [], function ($node) use ($rawPageData, &$pageData) {
                if (empty($node['input'])) {
                    return;
                }

                if ($rawPageData->offsetExists($node['key'])) {
                    $pageData[$node['key']] = $rawPageData[$node['key']];
                } elseif (isset($node['label']) && $node['label'] != '' && $rawPageData->offsetExists($node['label'])) {
                    $pageData[$node['key']] = $rawPageData[$node['label']];
                }
            });

            $pageItem['data'] = empty($pageData) ? null : $pageData;
            $pageItems[] = $pageItem;
        }

        return $pageItems;
    }

    protected function getNormalizedNameToNames(array $names): array
    {
        $normalizedNames = [];

        foreach ($names as $name) {
            $normalizedName = $name;

            if (! is_int($normalizedName)) {
                $normalizedName = mb_strtolower(preg_replace('/  +/', ' ', trim($normalizedName)));
            }

            if (array_key_exists($normalizedName, $normalizedNames)) {
                throw ValidationException::withMessages([
                    'file' => 'Columns "'.$name.'" and "'.$normalizedNames[$normalizedName].'" have equal names.'
                ]);
            }

            $normalizedNames[$normalizedName] = $name;
        }

        return $normalizedNames;
    }

    protected function updateModels(EloquentCollection $models, array $items, StepRequest $request): void
    {
        match ($this->workflow->type) {
            WorkflowType::Page => $this->updatePages($models, $items, $request),
        };
    }

    protected function updatePages(EloquentCollection $models, array $items, StepRequest $request): void
    {
        $updateItems = [];
        $emptyNameItems = [];

        foreach ($items as $item) {
            $validatedItem = $this->validatePageData($item);

            if (empty($validatedItem)) {
                continue;
            }

            if (isset($validatedItem['name'])) {
                if (isset($updateItems[$validatedItem['playlist_id'] ?? 0][$validatedItem['name']])) {
                    throw ValidationException::withMessages([
                        'name' => 'The name must be unique. The name "'.$validatedItem['name'].'" is not unique.',
                    ]);
                }

                $updateItems[$validatedItem['playlist_id'] ?? 0][$validatedItem['name']] = $validatedItem;
            } else {
                $emptyNameItems[] = $validatedItem;
            }
        }

        foreach ($emptyNameItems as $emptyNameItem) {
            $model = new Page();
            $this->setPageDataModel($model, $emptyNameItem, $request);
            $models->add($model);
        }

        foreach ($updateItems as $playlistId => $playlistItems) {
            foreach ($models as $model) {
                /** @var \App\Models\Page $model */

                if ($model->name == '' || ! isset($playlistItems[$model->name])) {
                    continue;
                }

                $isModelInPlaylist = false;

                if ($model->exists || $model->relationLoaded('playlistListingPivots')) {
                    $isModelInPlaylist = $model->playlistListingPivots->contains('playlist_id', $playlistId);
                }

                if (! $isModelInPlaylist) {
                    continue;
                }

                $this->setPageDataModel($model, $playlistItems[$model->name], $request);
                unset($playlistItems[$model->name]);
            }

            if (empty($playlistItems)) {
                continue;
            }

            /** @var \App\Models\Playlist $playlist */
            $playlist = Playlist::query()->find($playlistId);
            $existingModels = $playlist->pages()->whereIn('name', array_keys($playlistItems))->get();

            foreach ($existingModels as $existingModel) {
                /** @var \App\Models\Page $existingModel */
                $this->setPageDataModel($existingModel, $playlistItems[$existingModel->name], $request);
                $models->add($existingModel);

                unset($playlistItems[$existingModel->name]);
            }

            foreach ($playlistItems as $item) {
                $model = new Page();
                $this->setPageDataModel($model, $item, $request);
                $models->add($model);
            }
        }
    }
}
