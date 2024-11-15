<?php

namespace App\Services\Workflow\Tree;

use App\Services\Workflow\StepRequest;
use App\Services\Workflow\StepResponse;
use Tree\Node\Node as BaseNode;
use UnexpectedValueException;

class Node extends BaseNode implements NodeInterface
{
    public function handle(StepRequest $request): StepResponse
    {
        /** @var \App\Services\Workflow\StepInterface $step */
        $step = $this->getValue();
        $response = $step->handle($request);
        $children = $this->getChildren();

        if (
            $response->getParam('stop', false)
            || (! $response->hasParam('next_step_index') && empty($children))
        ) {
            return $response;
        }

        $nextStepIndex = $response->getParam('next_step_index', 0);

        if (! array_key_exists($nextStepIndex, $children)) {
            throw new UnexpectedValueException(
                'The step "'.$step::class.'" expects the next tree node to have a child with the key'
                    .' "'.$nextStepIndex.'".'
            );
        }

        /** @var \App\Services\Workflow\Tree\NodeInterface $nextNode */
        $nextNode = $children[$nextStepIndex];
        $nextParams = $request->getParam('steps')[$nextStepIndex];
        $nextParams['global'] = $request->getParam('global');
        $nextParams['prev_response_params'] = $response->getParams();

        return $nextNode->handle(new StepRequest($nextParams));
    }
}
