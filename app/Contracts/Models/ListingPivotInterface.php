<?php

namespace App\Contracts\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $group_id
 * @property int $sort_order
 * @property \App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model|null $group
 * @mixin \App\Contracts\Models\TreeSortable
 */
interface ListingPivotInterface
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function group();

    public function saveOrRestore(array $options = []);

    public function restoreIfTrashed();

    public function associateList($model): void;

    public function associateListable($model): void;

    public function isBelongedToList(Model|int $model): bool;
}
