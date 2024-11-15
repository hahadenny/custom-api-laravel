<?php

namespace App\Traits\Rules;

trait ValueFilterTrait
{
    protected function filterIds($ids): array
    {
        if (! is_array($ids)) {
            $ids = [$ids];
        }

        $ids = array_filter($ids, fn ($id) => $this->validateId($id));
        $ids = array_map(fn ($id) => (int) $id, $ids);

        return array_values($ids);
    }

    protected function validateIdOrIds($ids): bool
    {
        return is_array($ids) ? $this->validateIds($ids) : $this->validateId($ids);
    }

    protected function validateIds($ids): bool
    {
        if (! is_array($ids)) {
            return false;
        }

        foreach ($ids as $id) {
            if (! $this->validateId($id)) {
                return false;
            }
        }

        return true;
    }

    protected function validateId($id): bool
    {
        return filter_var($id, FILTER_VALIDATE_INT, ['min_range' => 1]) !== false;
    }

    protected function filterNames($names): array
    {
        if (! is_array($names)) {
            $names = [$names];
        }

        $names = array_filter($names, fn ($name) => is_scalar($name) && strlen($name) > 0);
        $names = array_map(fn ($name) => (string) $name, $names);

        return array_values($names);
    }

    protected function validateName($name): bool
    {
        return (is_string($name) || is_numeric($name)) && trim($name) !== '';
    }
}
