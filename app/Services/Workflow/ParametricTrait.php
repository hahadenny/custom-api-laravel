<?php

namespace App\Services\Workflow;

trait ParametricTrait
{
    protected array $params = [];

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): static
    {
        $this->params = $params;
        return $this;
    }

    public function hasParam(mixed $key): bool
    {
        return array_key_exists($key, $this->params);
    }

    public function getParam(mixed $key, $default = null): mixed
    {
        return array_key_exists($key, $this->params) ? $this->params[$key] : $default;
    }

    public function setParam(mixed $key, mixed $param): static
    {
        $this->params[$key] = $param;
        return $this;
    }

    public function unsetParam(mixed $key): static
    {
        unset($this->params[$key]);
        return $this;
    }

    public function addParam(mixed $param): static
    {
        $this->params[] = $param;
        return $this;
    }

    public function removeAllParam(mixed $param): static
    {
        foreach ($this->params as $key => $thisParam) {
            if ($thisParam === $param) {
                unset($this->params[$key]);
            }
        }
        return $this;
    }
}
