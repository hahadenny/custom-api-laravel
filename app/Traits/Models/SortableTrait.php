<?php

namespace App\Traits\Models;

use BadMethodCallException;
use Illuminate\Database\Eloquent\Builder;
use Spatie\EloquentSortable\SortableTrait as SortableBaseTrait;
use ValueError;

trait SortableTrait
{
    use SortableBaseTrait;

    public function moveToCurrentOrder(): void
    {
        $orderColumnName = $this->determineOrderColumnName();

        if ($this->buildSortQuery()->where($orderColumnName, $this->$orderColumnName)->whereKeyNot($this)->exists()) {
            $this->moveToOrder($this->$orderColumnName);
        }
    }

    /**
     * @param  int  $order Starting with one.
     * @return $this
     */
    public function moveToOrder(int $order): static
    {
        /** @var static|\Illuminate\Database\Eloquent\Model $this */

        $this->save();

        $this::moveManyToOrder([$this->getKey()], $order);

        $this->refresh();

        return $this;
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

        $ids = array_values($ids);

        while (! empty($ids)) {
            $id = array_shift($ids);

            /** @var static|null $model */
            $model = static::query()->find($id);
            if (is_null($model)) {
                continue;
            }

            /** @var \Illuminate\Database\Eloquent\Builder $query */
            $query = $model->buildSortQuery()->ordered();
            // all ids of this $model's class, constrained by buildSortQuery()
            $allIds = $query->pluck($model->getKeyName());
            // only the ids of the models we explicitly want to set the order of
            $newOrderIds = $allIds->intersect(array_merge($ids, [$id]))->values();
            // only the ids of the models we DIDN'T explicitly set the order of
            $newAllIds = $allIds->diff($newOrderIds)->values();
            // insert the new ids into the array of all ids we didn't set, at the position we want, without removing anything
            $newAllIds->splice($order - 1, 0, $newOrderIds);
            $newAllIds = $newAllIds->values();

            // set new order for all ids, based on their order in the $newAllIds array
            static::setNewOrder($newAllIds->toArray());
            // loop with what's leftover
            $ids = array_values(array_diff($ids, $allIds->toArray()));
        }
    }

    public function buildSortQuery(): Builder
    {
        throw new BadMethodCallException(
            'This method "'.__METHOD__.'" must be overridden by a child class.'
            .' Perhaps it should add some "where" clause to SQL query to specify list items that should be ordered.'
        );
    }
}
