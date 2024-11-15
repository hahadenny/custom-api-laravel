<?php

namespace App\Contracts\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $sort_order
 */
interface TreeSortable
{
    public function getHighestOrderNumber(): int;

    public function setHighestOrderNumber(): void;

    public function moveToCurrentOrder(): void;

    public function moveToOrder(int $order): void;

    public static function moveManyToOrder(array $ids, int $order): void;

    public function updateSortOrderOfList(): void;

    public function buildSortQuery(): Builder;
}
