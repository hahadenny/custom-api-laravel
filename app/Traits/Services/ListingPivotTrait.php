<?php

namespace App\Traits\Services;

use App\Contracts\Models\GroupInterface;
use App\Contracts\Models\ItemInterface;
use App\Contracts\Models\ListingPivotInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait ListingPivotTrait
{
    /**
     * @param int[]  $listingPivotIds All the listingPivotIds must belong to the same sorting list.
     * @param int    $order
     * @param string $pivotClass the listing pivot class that the IDs belong to
     *
     * @return void
     */
    protected function sortManyIdsToOrder(array $listingPivotIds, int $order, string $pivotClass): void
    {
        /** @var ListingPivotInterface $pivotClass */
        $pivotClass::moveManyToOrder($listingPivotIds, $order);
    }

    protected function pluckListingPivotIds(Collection|array $listingPivots)
    {
        return EloquentCollection::make($listingPivots)
                                 ->unique()->sortBy(['sort_order', 'id'])->values()->pluck('id')->all();
    }

    protected function sortManyToOrder(Collection|array $listingPivots, int $order): void
    {
        $listingPivotIds = $this->pluckListingPivotIds($listingPivots);
        $this->sortManyIdsToOrder($listingPivotIds, $order, $listingPivots[0]::class);
    }

    protected function saveReplicatedListingPivot(
        ListingPivotInterface $newListingPivot, ItemInterface|GroupInterface|Model $newListable,
        ?int $groupId, ListingPivotInterface $oldListingPivot
    ): void
    {
        $newListingPivot->group()->associate($groupId);
        $newListingPivot->associateListable($newListable);
        $newListingPivot->sort_order = $oldListingPivot->sort_order;

        if (is_null($newListingPivot->sort_order)) {
            $newListingPivot->setHighestOrderNumber();
        }

        $newListingPivot->saveOrRestore();
    }
}
