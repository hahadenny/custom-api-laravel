<?php

namespace App\Rules;

use App\Traits\Rules\ValueFilterTrait;
use BadMethodCallException;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ImplicitRule;
use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\QueryBuilder;

/**
 * The rule is applied on a "parent id" field.
 * It checks the depth of: ... -> [parent] -> [new | middle] -> [children] -> ... .
 * Any of these nodes may not be specified.
 *
 * This uses the "level" term, that is like the "depth", but the "level" starts from 1.
 */
abstract class NodeDepthAbstract implements DataAwareRule, ImplicitRule
{
    use ValueFilterTrait;

    protected string $modelClass;

    /**
     * Zero-based.
     */
    protected int|null $max;

    /**
     * Zero-based.
     */
    protected int $defaultMax = 2;

    protected bool $isCreatingNode = false;

    protected Model|int|null $middle = null;

    protected string|null $childrenIdFieldName = null;

    /**
     * @var int[]|null
     */
    protected mixed $childrenIds = null;

    protected array $data;

    /**
     * Create a new rule instance.
     *
     * @param  string  $modelClass
     * @param  int|null  $max Zero-based.
     */
    public function __construct(string $modelClass, ?int $max = null)
    {
        $this->modelClass = $modelClass;
        $this->max = $max;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function setMiddle(Model|int|null $middle): static
    {
        $this->middle = $middle;
        return $this;
    }

    public function setChildrenIdFieldName(string|null $name): static
    {
        if (! is_null($name) && ! is_null($this->childrenIds)) {
            throw new BadMethodCallException('If "childrenIdFieldName" is being set, "childrenIds" must not be set.');
        }

        $this->childrenIdFieldName = $name;

        return $this;
    }

    public function setChildrenIds($ids): static
    {
        if (! is_null($ids) && ! is_null($this->childrenIdFieldName)) {
            throw new BadMethodCallException('If "childrenIds" is being set, "childrenIdFieldName" must not be set.');
        }

        $this->childrenIds = $ids;

        return $this;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $parentId = $value;

        if (! is_null($parentId) && ! $this->validateId($parentId)) {
            return false;
        }

        $childrenIds = null;

        if (! is_null($this->childrenIds)) {
            $childrenIds = $this->childrenIds;
        } elseif (! is_null($this->childrenIdFieldName)) {
            if (array_key_exists($this->childrenIdFieldName, $this->data)) {
                $childrenIds = $this->data[$this->childrenIdFieldName];
            }
        }

        if (! is_null($childrenIds) && ! $this->validateIds($childrenIds)) {
            return false;
        }

        $level = 0;

        if (! is_null($parentId)) {
            $level += $this->getLevel($parentId);
        }

        if ($this->isCreatingNode) {
            ++$level;
        } elseif (! is_null($this->middle)) {
            $middleId = $this->middle instanceof Model ? $this->middle->id : $this->middle;

            $tmpLevel = $this->getLeafRelativeMaxLevel([$middleId]);

            if (($level + $tmpLevel - 1) > ($this->max ?? $this->defaultMax)) {
                return false;
            }

            ++$level;
        }

        if (! is_null($childrenIds)) {
            $level += $this->getLeafRelativeMaxLevel($childrenIds);
        }

        $depth = $level === 0 ? null : $level - 1;

        // If $depth is "null", then there is no data to validate.
        return is_null($depth) || $depth <= ($this->max ?? $this->defaultMax);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return trans('messages.validation.node_depth');
    }

    protected function getLevel(int $id): int
    {
        /** @var \Kalnoy\Nestedset\NodeTrait|null $node */
        $node = $this->newModelQuery()->withDepth()->whereKey($id)->first();
        return is_null($node) ? 0 : $node->depth + 1;
    }

    /**
     * @param  int[]  $ids
     * @return int
     */
    protected function getLeafRelativeMaxLevel(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $relativeMaxLevel = 0;

        $query = $this->newModelQuery();

        $nodes = $query->clone()->withDepth()->whereKey($ids)->get();

        foreach ($nodes as $node) {
            /** @var \Kalnoy\Nestedset\NodeTrait $node */

            /** @var \Kalnoy\Nestedset\QueryBuilder|\Illuminate\Database\Query\Builder $from */
            $from = $query->clone()->withDepth()->whereDescendantOrSelf($node)->whereIsLeaf();
            $nodeMaxDepth = $query->clone()->newQuery()->getQuery()->from($from)->max('depth');
            $relativeNodeMaxLevel = $nodeMaxDepth - $node->depth + 1;

            if ($relativeMaxLevel < $relativeNodeMaxLevel) {
                $relativeMaxLevel = $relativeNodeMaxLevel;
            }
        }

        return $relativeMaxLevel;
    }

    protected function newModelQuery(): QueryBuilder
    {
        /** @var \Illuminate\Database\Eloquent\Model|\Kalnoy\Nestedset\NodeTrait $model */
        $model = new $this->modelClass;
        /** @var \Kalnoy\Nestedset\QueryBuilder $query */
        $query = $model->newQuery();

        return $query;
    }
}
