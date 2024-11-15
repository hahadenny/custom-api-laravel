<?php

namespace App\Services\Workflow;

interface StepInterface
{
    public function handle(StepRequest $request): StepResponse;
}
