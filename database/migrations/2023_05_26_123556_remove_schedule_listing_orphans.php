<?php

use App\Models\Schedule\ScheduleListing;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration {
    public function up() : void
    {
        if(ScheduleListing::isBroken()){
            Log::warning('SCHEDULE LISTING TREE IS BROKEN :'.json_encode(ScheduleListing::countErrors()));

            ScheduleListing::fixTree();
        }

        $badListings = ScheduleListing::withTrashed()->where(function($query){
            // listings with no parent or set
            $query->whereNull('parent_id')
                  ->whereNull('schedule_set_id');
        })->orWhere(function($query){
            // non-layer root listings
            $query->whereIsRoot()
                  ->whereNot('scheduleable_type', 'App\Models\ChannelLayer');
        })->get();
        // delete each separately to cascade delete any of its nested descendants
        foreach($badListings as $badListing){
            $badListing->forceDelete();
        }

        // In case old, bad data is still around (e.g., deleted playlists whose page descendants weren't deleted),
        // find deleted ancestors and delete again to ensure their descendants are properly deleted
        $deleted = ScheduleListing::onlyTrashed()
                                  ->whereNot('scheduleable_type', 'App\Models\Page')
                                  ->get();
        foreach($deleted as $deleteListing){
            $deleteListing->delete();
        }
    }
};
