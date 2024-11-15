<?php

namespace App\Services\Workflow\Steps;

use App\Services\Workflow\StepRequest;
use App\Services\Workflow\StepResponse;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class SetDataStep extends Step
{
    use SetDataTrait;

    public function handle(StepRequest $request): StepResponse
    {
        $prevResponseParams = $request->getParam('prev_response_params');
        $models = $prevResponseParams['models'] ?? new EloquentCollection();

        $data = $this->validateData($request->getParam('data', []));

        if (! empty($data)) {
            foreach ($models as $model) {
                /** @var \Illuminate\Database\Eloquent\Model $model */
                $this->setDataModel($model, $data, $request);
            }
        }

        return new StepResponse(['models' => $models]);
    }
}
