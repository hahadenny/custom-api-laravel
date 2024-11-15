<?php

namespace App\Services\Workflow\Steps;

use App\Enums\WorkflowType;
use App\Models\Company;
use App\Models\Page;
use App\Models\Playlist;
use App\Services\Workflow\StepRequest;
use App\Services\Workflow\StepResponse;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use JWadhams\JsonLogic;

class FilterStep extends Step
{
    public function handle(StepRequest $request): StepResponse
    {
        $params = $request->getParam('global')['params'];

        $prevResponseParams = $request->getParam('prev_response_params', []);
        $rules = $this->modifyRules($request->getParam('rules', []));
        $models = array_key_exists('models', $prevResponseParams)
            ? $prevResponseParams['models']
            : $this->getQuery($params)->get();
        $filteredModels = new EloquentCollection();

        foreach ($models as $model) {
            /** @var \Illuminate\Database\Eloquent\Model $model */

            if (JsonLogic::apply($rules, $model->toArray())) {
                $filteredModels->add($model);
            }
        }

        return new StepResponse(['models' => $filteredModels]);
    }

    protected function getQuery(array $params): BuilderContract
    {
        return match ($this->workflow->type) {
            WorkflowType::Page => $this->getPageQuery($this->workflow->company, $params),
        };
    }

    protected function getPageQuery(Company $company, array $params): BuilderContract
    {
        $query = $company->playlistPages()
            ->with(['playlistListingPivots'])
            ->with(['playlists.parentListingPivot'])
            ->wherePivotNotNull('playlist_id');

        if (array_key_exists('playlist_id', $params)) {
            /** @var \App\Models\Playlist $playlist */
            $playlist = Playlist::query()->find($params['playlist_id']);
            $query = $playlist->pages();
        }

        $query
            ->with(['playlists', 'template', 'channel'])
            ->where('pages.company_id', $company->id)
            ->orderBy('pages.id');

        if (array_key_exists('page_ids', $params)) {
            $query->whereKey($params['page_ids']);
        }

        return $query;
    }

    protected function modifyRules(array $rules): array
    {
        return match ($this->workflow->type) {
            WorkflowType::Page => $this->modifyPageRules($rules),
            default => $rules,
        };
    }

    protected function modifyPageRules(array $rules): array
    {
        return $this->modifyPageRulesRecursive($rules);
    }

    protected function modifyPageRulesRecursive(array $rules): array
    {
        $modifiedRules = [];

        foreach ($rules as $key => $value) {
            $modifiedKey = $key;
            $modifiedValue = $value;

            if (in_array($key, ['==', '==='], true)) {
                $var = $value[0]['var'] ?? '';

                if ($var === 'playlist_id') {
                    $modifiedKey = 'some';
                    $modifiedValue = [
                        ['var' => 'playlists'],
                        [$key => [['var' => 'id'], $value[1]]],
                    ];
                } elseif ($var === 'playlist_page_id') {
                    [$playlistId, $pageId] = explode(',', $value[1]);

                    $modifiedKey = 'and';
                    $modifiedValue = [
                        ['==' => [['var' => 'playlist_id'], $playlistId]],
                        ['==' => [['var' => 'id'], $pageId]],
                    ];
                }
            }

            $modifiedRules[$modifiedKey] = is_array($modifiedValue)
                ? $this->modifyPageRulesRecursive($modifiedValue)
                : $modifiedValue;
        }

        return $modifiedRules;
    }
}
