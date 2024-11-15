<?php

namespace App\Rules;

/**
 * This checks the depth of: ... -> [parent] -> new -> [children] -> ... .
 */
class NodeDepthNewNode extends NodeDepthAbstract
{
    /**
     * Create a new rule instance.
     *
     * @param  string  $modelClass
     * @param  int|null  $max Zero-based.
     */
    public function __construct(string $modelClass, ?int $max = null)
    {
        parent::__construct($modelClass, $max);

        $this->isCreatingNode = true;
    }
}
