<?php

namespace App\Traits\Models;

use App\Contracts\Models\GroupInterface;
use App\Contracts\Models\TreeSortable;
use BadMethodCallException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;
use ValueError;

/**
 * @mixin \App\Contracts\Models\TreeSortable
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait TreeSortableTrait
{
    public static function bootTreeSortableTrait()
    {
        static::creating(function (TreeSortable $model) {
            if (is_null($model->sort_order)) {
                $model->setHighestOrderNumber();
            }
        });
    }

    /**
     * Components (\App\Contracts\Models\ItemInterface, \App\Contracts\Models\GroupInterface) of the tree
     * must have a pivot (\Illuminate\Database\Eloquent\Relations\Pivot) property
     * that is a model of *_listings tables. This is ensured by relations.
     *
     * @return \Illuminate\Support\Collection
     */
    abstract protected function buildTreeToUpdateSort(): Collection;

    public function getHighestOrderNumber(): int
    {
        return (int) $this->buildSortQuery()->max('sort_order');
    }

    public function setHighestOrderNumber(): void
    {
        $this->sort_order = $this->getHighestOrderNumber() + 1;
    }

    public function moveToCurrentOrder(): void
    {
        $this->moveToOrder($this->sort_order);
    }

    /**
     * @param  int  $order Starting with one.
     * @return void
     */
    public function moveToOrder(int $order): void
    {
        $this::moveManyToOrder([$this->getKey()], $order);
        $this->refresh();
    }

    /**
     * @param  int[]  $ids
     * @param  int  $order Starting with one.
     * @return void
     */
    public static function moveManyToOrder(array $ids, int $order): void
    {
        if ($order < 1) {
            throw new ValueError('The "order" must be greater than or equal to 1.');
        }
        if (empty($ids)) {
            return;
        }

        $ids = array_values($ids);

        /** @var static $model */
        $model = static::query()->find($ids[0]);

        static::moveManyToOrderRecursive($model->buildTreeToUpdateSort(), $ids, $order, 1);
    }

    public function updateSortOrderOfList(): void
    {
        static::updateSortOrderRecursive($this->buildTreeToUpdateSort(), 1);
    }

    /**
     * @param  \Illuminate\Support\Collection  $tree
     * @param  int[]  $ids
     * @param  int  $order Starting with one.
     * @param  int  $currentOrder
     * @return int
     */
    protected static function moveManyToOrderRecursive(Collection $tree, array $ids, int $order, int $currentOrder): int
    {
        if ($tree->isEmpty()) {
            return $currentOrder;
        }

        if (! empty($ids)) {
            $moveKeys = [];
            $moveModels = [];
            $foundIds = [];

            foreach ($tree as $key => $model) {
                /** @var \App\Contracts\Models\ItemInterface|\App\Contracts\Models\GroupInterface $model */
                /** @var \Illuminate\Database\Eloquent\Relations\Pivot $pivot */
                $pivot = $model->pivot;
                $idIndex = array_search($pivot->getKey(), $ids);

                if ($idIndex !== false) {
                    $moveKeys[] = $key;
                    $moveModels[] = $model;
                    $foundIds[$idIndex] = $pivot->getKey();

                    unset($ids[$idIndex]);
                    $ids = array_values($ids);
                }
            }

            $moveModels = collect($moveModels)
                ->sortBy(fn ($model) => array_search($model->pivot->getKey(), $foundIds))
                ->values()
                ->all();

            if (! empty($moveKeys)) {
                $tree = $tree->forget($moveKeys)->values();
                $positionKey = 0;

                foreach ($tree as $key => $model) {
                    /** @var \App\Contracts\Models\ItemInterface|\App\Contracts\Models\GroupInterface $model */
                    /** @var \Illuminate\Database\Eloquent\Relations\Pivot $pivot */
                    $pivot = $model->pivot;

                    $positionKey = $key;

                    if ($pivot->sort_order >= $order) {
                        break;
                    }

                    $positionKey++;
                }

                $tree->splice($positionKey, 0, $moveModels);
                $tree = $tree->values();
            }
        }

        foreach ($tree as $model) {
            /** @var \App\Contracts\Models\ItemInterface|\App\Contracts\Models\GroupInterface $model */

            $pivot = static::castPivot($model->pivot);

            $pivot->sort_order = $currentOrder;
            $pivot->update();
            $currentOrder++;

            if ($model instanceof GroupInterface) {
                $currentOrder = static::moveManyToOrderRecursive($model->components, $ids, $order, $currentOrder);
            }
        }

        return $currentOrder;
    }

    protected static function updateSortOrderRecursive(Collection $tree, int $currentOrder): int
    {
        if ($tree->isEmpty()) {
            return $currentOrder;
        }

        foreach ($tree as $model) {
            /** @var \App\Contracts\Models\ItemInterface|\App\Contracts\Models\GroupInterface $model */

            $pivot = static::castPivot($model->pivot);

            $pivot->sort_order = $currentOrder;
            $pivot->update();
            $currentOrder++;

            if ($model instanceof GroupInterface) {
                $currentOrder = static::updateSortOrderRecursive($model->components, $currentOrder);
            }
        }

        return $currentOrder;
    }

    protected static function castPivot(Pivot $pivot): static
    {
        /** @var static $listingPivot */
        $listingPivot = static::query()->hydrate([$pivot->getOriginal()])->first();

        foreach ($pivot->getAttributes() as $name => $value) {
            $listingPivot->{$name} = $value;
        }

        return $listingPivot;
    }

    public function buildSortQuery(): Builder
    {
        throw new BadMethodCallException(
            'This method "'.__METHOD__.'" must be overridden by a child class.'
            .' Perhaps it should add some "where" clause to SQL query to specify list items that should be ordered.'
        );
    }
}
