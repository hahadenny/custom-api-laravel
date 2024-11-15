<?php

namespace App\Services\Workflow\Steps;

use App\Enums\ChannelEntityType;
use App\Enums\WorkflowType;
use App\Models\Channel;
use App\Models\Playlist;
use App\Models\PlaylistListing;
use App\Services\PageService;
use App\Services\Workflow\StepRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

trait SetDataTrait
{
    protected function validateData(array $data): array
    {
        return match ($this->workflow->type) {
            WorkflowType::Page => $this->validatePageData($data),
        };
    }

    protected function validatePageData(array $data): array
    {
        $company = $this->workflow->company;
        $channelEntityTable = ChannelEntityType::getTableFrom($data['channel_entity_type'] ?? null);

        $validatedData = Validator::validate($data, [
            'playlist_id' => [
                Rule::when(
                    array_key_exists('page_group_id', $data)
                        || array_key_exists('sort_order', $data),
                    [
                        'present',
                    ]
                ),
                'nullable',
                'integer',
                Rule::exists('playlists', 'id')->where(function ($query) use ($company) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $company->id)
                        ->whereNull('deleted_at');
                }),
            ],
            'page_group_id' => [
                'nullable',
                'integer',
                Rule::exists('page_groups', 'id')->where(function ($query) use ($company, $data) {
                    $playlistId = 0;
                    if (array_key_exists('playlist_id', $data)) {
                        if (is_null($data['playlist_id'])) {
                            $playlistId = null;
                        } elseif (is_numeric($data['playlist_id'])) {
                            $playlistId = (int) $data['playlist_id'];
                        }
                    }

                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('playlist_id', $playlistId)
                        ->where('company_id', $company->id)
                        ->whereNull('deleted_at');
                }),
            ],
            'template_id' => [
                'nullable',
                'integer',
                Rule::exists('templates', 'id')->where(function ($query) use ($company) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $company->id)
                        ->whereNull('deleted_at');
                }),
            ],
            'channel_id' => [
                'nullable',
                'integer',
                Rule::exists('channels', 'id')->where(function ($query) use ($company) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    return $query
                        ->where('company_id', $company->id)
                        ->whereNull('deleted_at');
                }),
            ],
            'channel_entity_type' => [
                'nullable',
                Rule::when(! empty($data['channel_entity_id']), [
                    'required',
                ]),
                new Enum(ChannelEntityType::class),
            ],
            'channel_entity_id' => [
                'nullable',
                'integer',
                Rule::when(! is_null($channelEntityTable), [
                    Rule::exists($channelEntityTable, 'id')->where(function ($query) use ($company) {
                        /** @var \Illuminate\Database\Query\Builder $query */
                        return $query
                            ->where('company_id', $company->id)
                            ->whereNull('deleted_at');
                    }),
                ]),
            ],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'description' => 'nullable|string',
            'is_live' => 'boolean',
            'page_number' => 'sometimes|integer|min:0',
            'data' => 'nullable|array',
            'sort_order' => 'nullable|integer|min:1',
        ]);

        foreach ($data as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'data.')) {
                Validator::validate(['data_key' => trim(substr($key, strlen('data.')))], [
                    'data_key' => 'required',
                ]);

                $validatedData[$key] = $value;
            }
        }

        return $validatedData;
    }

    protected function setDataModel(Model $model, array $data, StepRequest $request): void
    {
        match ($this->workflow->type) {
            WorkflowType::Page => $this->setPageDataModel($model, $data, $request),
        };
    }

    protected function setPageDataModel(Model $model, array $data, StepRequest $request): void
    {
        /** @var \App\Models\Page $model */

        /** @var \App\Models\User $user */
        $user = $request->getParam('global')['user'];

        $attributeNames = [
            'company_id',
            'template_id',
            'channel_id',
            'channel_entity_type',
            'channel_entity_id',
            'name',
            'description',
            'is_live',
            'has_media',
            'page_number',
            'data',
        ];

        foreach ($data as $name => $value) {
            if (in_array($name, $attributeNames, true)) {
                $model->{$name} = $value;
            }
        }

        if (is_null($model->page_number)) {
            $model->page_number = 0;
        }

        if (
            ! $model->exists
            || array_key_exists('page_group_id', $data)
            || array_key_exists('sort_order', $data)
        ) {
            // If the model does not exist, playlist_id must be present.
            Validator::validate($data, [
                'playlist_id' => 'present',
            ]);

            /** @var \App\Models\Playlist|null $playlist */
            $playlist = is_null($data['playlist_id']) ? null : Playlist::query()->find($data['playlist_id']);
            /** @var \App\Services\PageService $pageService */
            $pageService = app(PageService::class);

            $pageService->setParentModel($playlist);
            $model->setParentModel($playlist);

            $listingPivot = null;

            if ($model->relationLoaded('playlistListingPivots')) {
                $listingPivot = $model->playlistListingPivots
                    ->where('playlist_id', $playlist?->id)
                    ->where('company_id', $this->workflow->company_id)
                    ->first();
            }

            if (is_null($listingPivot)) {
                if ($model->exists) {
                    $listingPivot = $pageService->getItemListingPivot($model);
                } else {
                    // The $listingPivot cannot be associated with the $model because the $model (id) does not exist.
                    // The $listingPivot must be associated with the $model after the $model is saved.
                    $listingPivot = new PlaylistListing([
                        'playlist_id' => $playlist?->id,
                        'company_id' => $this->workflow->company_id,
                    ]);
                }

                $model->playlistListingPivots->add($listingPivot);
            }

            if (array_key_exists('page_group_id', $data)) {
                $listingPivot->group()->associate($data['page_group_id']);
            }

            if (array_key_exists('sort_order', $data)) {
                $listingPivot->sort_order = $data['sort_order'];
            } elseif (is_null($listingPivot->sort_order)) {
                $listingPivot->setHighestOrderNumber();
            }
        }

        $defaultChannel = $this->workflow->company->channels()->defaultOfType($model->template?->engine)->first();

        if (is_null($model->channel_id)) {
            $model->channel()->associate($defaultChannel);
        }
        if (is_null($model->channel_entity_id)) {
            $model->channelEntity()->associate($defaultChannel);
        }

        // Sync channel_id and channel_entity_id.
        if (array_key_exists('channel_id', $data) && ! array_key_exists('channel_entity_id', $data)) {
            $model->channelEntity()->associate($model->channel);
        } elseif (! array_key_exists('channel_id', $data) && array_key_exists('channel_entity_id', $data)) {
            if (is_null($model->channelEntity) || $model->channelEntity instanceof Channel) {
                $model->channel()->associate($model->channelEntity);
            }
        }

        if (! $model->exists) {
            $model->company()->associate($this->workflow->company);
            $model->createdBy()->associate($user);
        }

        $pageData = $model->data ?? [];
        foreach ($request->getParam('data', []) as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'data.')) {
                $key = trim(substr($key, strlen('data.')));
                if ($key === '') {
                    continue;
                    //throw new WorkflowException('The page data key must not be empty.');
                }
                data_set($pageData, $key, $value);
            }
        }
        $model->data = $pageData;
    }
}
