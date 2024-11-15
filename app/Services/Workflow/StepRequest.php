<?php

namespace App\Services\Workflow;

class StepRequest
{
    use ParametricTrait;

    public function __construct(array $params = [])
    {
        $this->params = $params;
    }
}
