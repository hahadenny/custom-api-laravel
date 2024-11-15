<?php

namespace App\Services\Workflow\Tree;

use App\Services\Workflow\StepRequest;
use App\Services\Workflow\StepResponse;
use Tree\Node\NodeInterface as BaseNodeInterface;

interface NodeInterface extends BaseNodeInterface
{
    public function handle(StepRequest $request): StepResponse;
}
