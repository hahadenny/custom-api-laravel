<?php

namespace App\Services\Workflow\Steps;

use App\Enums\WorkflowType;
use App\Models\PlaylistListing;
use App\Services\PageService;
use App\Services\Workflow\StepRequest;
use App\Services\Workflow\StepResponse;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class SaveStep extends Step
{
    public function handle(StepRequest $request): StepResponse
    {
        $prevResponseParams = $request->getParam('prev_response_params');
        $models = $prevResponseParams['models'] ?? new EloquentCollection();

        $this->validateModels($models);

        foreach ($models as $model) {
            /** @var \Illuminate\Database\Eloquent\Model $model */
            $this->saveDataModel($model);
        }

        $this->saveOtherData($models);

        return new StepResponse(['models' => $models]);
    }

    /**
     * Perform validation that cannot be performed in a "set data" step.
     * For example, a "set data" step cannot validate data against a "required" rule.
     * Because the step does not know whether it is the last "set data" step or there will be
     * another "set data" step that will set the required data.
     */
    protected function validateModels(EloquentCollection $models): void
    {
        match ($this->workflow->type) {
            WorkflowType::Page => $this->validatePages($models),
        };
    }

    protected function validatePages(EloquentCollection $models): void
    {
        foreach ($models as $model) {
            /** @var \App\Models\Page $model */

            $data = [];

            if (array_key_exists('name', $model->getAttributes())) {
                $data['name'] = $model->name;
            }

            // Only validate against a "present" rule.
            // Other validation should be performed in "set data" step.
            // It is assumed if a field is present, then it has been validated in "set data" step.
            Validator::validate($data, [
                'name' => 'present',
            ]);
        }
    }

    protected function saveDataModel(Model $model): void
    {
        match ($this->workflow->type) {
            WorkflowType::Page => $this->savePageDataModel($model),
        };
    }

    protected function savePageDataModel(Model $model): void
    {
        /** @var \App\Models\Page $model */

        $modelExists = $model->exists;

        $model->save();

        if (! $modelExists) {
            foreach ($model->playlistListingPivots as $listingPivot) {
                // If the $model has not existed before saving, then the $listingPivot must be associated with it.
                // Because "Set Data" step can set $model->playlistListingPivots but cannot associate they.
                $listingPivot->playlistable()->associate($model);
            }
        }
    }

    /**
     * Save other data that can only be saved after all models are saved.
     */
    protected function saveOtherData(EloquentCollection $models): void
    {
        match ($this->workflow->type) {
            WorkflowType::Page => $this->savePageOtherData($models),
        };
    }

    protected function savePageOtherData(EloquentCollection $models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $groupedByCompany = $models
            ->filter(fn (Model $model) => $model->relationLoaded('playlistListingPivots'))
            ->values()
            ->pluck('playlistListingPivots')
            ->flatten()
            ->filter(function (PlaylistListing $playlistListing): bool {
                return ! $playlistListing->exists
                    || $playlistListing->trashed()
                    || ! empty($playlistListing->getDirty());
            })
            ->sortBy(fn (PlaylistListing $playlistListing) => $playlistListing->getOriginal('sort_order'))
            ->values()
            ->each(function (PlaylistListing $playlistListing) {
                $playlistListing->saveOrRestore();
            })
            ->groupBy(['company_id', 'playlist_id', 'sort_order']);

        foreach ($groupedByCompany as $groupedByPlaylist) {
            foreach ($groupedByPlaylist as $groupedBySortOrder) {
                foreach ($groupedBySortOrder as $sortOrder => $listingPivots) {
                    /** @var \Illuminate\Support\Collection $listingPivots */

                    PlaylistListing::moveManyToOrder($listingPivots->pluck('id')->values()->all(), $sortOrder);
                }
            }
        }

        /** @var \App\Services\PageService $pageService */
        $pageService = app(PageService::class);

        foreach ($models as $model) {
            /** @var \App\Models\Page $model */

            $model->name = $pageService->replicateUniqueNameOfItem($model);
            $model->save();
        }
    }
}
