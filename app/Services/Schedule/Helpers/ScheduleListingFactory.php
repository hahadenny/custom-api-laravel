<?php

namespace App\Services\Schedule\Helpers;

use App\Models\PlaylistListing;
use App\Models\ProjectListing;
use App\Models\Schedule\ScheduleListing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ScheduleListingFactory
{
    /**
     * Create or restore a ScheduleListing with a given parent.
     *
     * @throws \ErrorException
     */
    protected function create(array $attributes, ?ScheduleListing $parentScheduleListingPivot) : ScheduleListing
    {
        $scheduleListing = new ScheduleListing($attributes);
        $scheduleListing->appendToNode($parentScheduleListingPivot);
        // **NOTE**: attempting to mute events will break the nested set tree (_lft and _rgt will be set to 0)
        $scheduleListing->saveOrRestore();

        if(ScheduleListing::isBroken()){
            Log::warning(
                'SCHEDULE LISTING TREE IS BROKEN :'.json_encode(ScheduleListing::countErrors()),
                // context
                ['schedule_listing_id' => $scheduleListing->id]
            );
        }

        return $scheduleListing;
    }

    /**
     * Create new or restore ScheduleListings based on the given $originalListing
     *
     * @returns ScheduleListing[]
     *
     * @throws \ErrorException
     */
    public function createManyFrom(
        ProjectListing|PlaylistListing $originalListing,
        array|Collection               $parentScheduleListingPivots = null,
    ) : array
    {
        if ($originalListing instanceof ProjectListing) {
            $scheduleable_id_col = 'projectable_id';
            $scheduleable_type_col = 'projectable_type';
        } else {
            $scheduleable_id_col = 'playlistable_id';
            $scheduleable_type_col = 'playlistable_type';
        }

        // parent entries in schedule listing table that we will attach to
        $parentScheduleListingPivots ??= ScheduleListing::whereHasListingParent($originalListing)->get();

        $scheduleListingPivots = [];
        foreach ($parentScheduleListingPivots as $parentScheduleListingPivot) {
            $scheduleListingPivot = $this->create([
                'listing_id'        => $originalListing->id,
                'listing_type'      => $originalListing::class,
                'scheduleable_id'   => $originalListing->$scheduleable_id_col,
                'scheduleable_type' => $originalListing->$scheduleable_type_col,
                'sort_order'        => $originalListing->sort_order,
            ], $parentScheduleListingPivot);
            $scheduleListingPivots []= $scheduleListingPivot;
        }

        if(ScheduleListing::isBroken()){
            Log::warning(
                'TREE IS BROKEN :'.json_encode(ScheduleListing::countErrors()),
                // context
                [
                    'listing_id'        => $originalListing->id,
                    'listing_type'      => $originalListing::class,
                    'scheduleable_id'   => $originalListing->$scheduleable_id_col,
                    'scheduleable_type' => $originalListing->$scheduleable_type_col,
                ]
            );
        }

        return $scheduleListingPivots;
    }
}
