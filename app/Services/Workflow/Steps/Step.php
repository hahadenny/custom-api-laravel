<?php

namespace App\Services\Workflow\Steps;

use App\Models\Workflow;
use App\Services\Workflow\StepInterface;

abstract class Step implements StepInterface
{
    protected Workflow $workflow;

    public function setWorkflow(Workflow $workflow): static
    {
        $this->workflow = $workflow;
        return $this;
    }
}
