<?php

namespace App\Services\Workflow;

class StepResponse
{
    use ParametricTrait;

    public function __construct(array $params = [])
    {
        $this->params = $params;
    }
}
