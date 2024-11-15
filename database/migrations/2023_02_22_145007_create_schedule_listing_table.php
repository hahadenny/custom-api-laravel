<?php

use App\Models\Schedule\Schedule;
use App\Models\Schedule\ScheduleListing;
use App\Models\Schedule\ScheduleSet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('schedule_listings', function (Blueprint $table) {
            $table->id();
            $table->nestedSet();
            $table->foreignId('schedule_set_id')->nullable()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            // PlaylistListing or ProjectListing
            $table->nullableMorphs('listing');
            // the listing item (layer / playlist / page)
            $table->morphs('scheduleable');
            $table->foreignId('schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('duration')->default(0);
            // playing, paused, playing next...
            $table->string('status')->nullable();
            $table->unsignedBigInteger('sort_order');
            $table->timestamps();
            $table->softDeletes();
        });

        // drop these and add schedules FK to schedule_listings so that accessing
        // schedule listing related data is cleaner
        Schema::table('schedules', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $tableIndexes = $sm->listTableIndexes('schedules');
            $indexesToCheck = [
                'schedules_scheduleable_id_scheduleable_type_origin_unique',
            ];
            foreach ($indexesToCheck as $currentIndex) {
                if (array_key_exists($currentIndex, $tableIndexes)) {
                    // The current index exists in the table, do something here :)
                    $table->dropUnique(['scheduleable_id', 'scheduleable_type', 'origin']);
                }
            }

            // not scheduleable dropMorphs because the index was already removed and
            // replaced with the above origin index ^
            $table->dropColumn('scheduleable_id');
            $table->dropColumn('scheduleable_type');
            $table->dropMorphs('parentable');
            $table->dropColumn('ranges');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->renameColumn('duration', 'default_duration');
        });

        $this->initListings();

        // add schedules to schedule_listings. same models will be given the same schedules this time around
        $schedules = Schedule::where('origin', \App\Enums\Schedule\ScheduleOrigin::Scheduled)->get();
        foreach ($schedules as $schedule) {
            $parents = ScheduleListing::select('id')->where('scheduleable_id', $schedule->parentable_id)
                                      ->where('scheduleable_type', $schedule->parentable_type)
                                      ->get();
            foreach($parents as $parent){
                ScheduleListing::where('scheduleable_id', $schedule->scheduleable_id)
                               ->where('scheduleable_type', $schedule->scheduleable_type)
                               ->where('parent_id', $parent->id)
                               ->update(['schedule_id' => $schedule->id]);
            }
        }
    }

    public function down()
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('ranges')->nullable();
            // morphs() because re-adding the unique origin index is not worth the hassle
            $table->morphs('scheduleable');
            $table->nullableMorphs('parentable');
        });

        /*// return schedules data from schedule_listings to schedules table
        $schedule_listings = ScheduleListing::with('parent')->get();
        foreach ($schedule_listings as $schedule_listing) {
            Schedule::where('id', $schedule_listing->schedule_id)
                    ->update([
                        'scheduleable_type' => $schedule_listing->scheduleable_type,
                        'scheduleable_id' => $schedule_listing->scheduleable_id,
                        'parentable_type' => $schedule_listing->parent?->scheduleable_type,
                        'parentable_id' => $schedule_listing->parent?->scheduleable_id,
                    ]);
        }*/

        Schema::table('pages', function (Blueprint $table) {
            $table->renameColumn('default_duration', 'duration');
        });

        Schema::dropIfExists('schedule_listings');

    }

    private function initListings()
    {
        $scheduleSets = ScheduleSet::whereNull('deleted_at')->get();
        foreach ($scheduleSets as $scheduleSet){
            $this->initListing($scheduleSet);
        }
    }

    // only copying what already exists in the old schedule listings
    private function initListing(ScheduleSet $scheduleSet){
        foreach($scheduleSet->layers as $layer){
            // create in schedule listing
            $scheduleListing = ScheduleListing::firstOrNew([
                'schedule_set_id' => $scheduleSet->id,
                'scheduleable_id' => $layer->id,
                'scheduleable_type' => $layer::class,
            ]);
            if (isset($params['sort_order'])) {
                $scheduleListing->moveToOrder($params['sort_order']);
            } else {
                $scheduleListing->setHighestOrderNumber();
            }
            $scheduleListing->save();

            // dump('Layer --> '.$layer->name);

            /*$parentNode = $scheduleListing;

            $playlist_ids = ScheduleLayerListing::where('channel_layer_id', $layer->id)->where('layerable_type', Playlist::class)->get()->pluck('layerable_id')->all();
            $schedulePlaylistListings = SchedulePlaylistListing::where('deleted_at', '=', null)
                                                       ->where('playlistable_type', Page::class)
                                                       ->whereRelation('playlist', 'deleted_at', '=', null)
                                                       ->whereNotNull('playlist_id')
                                                       ->whereRelation('playlistable', 'deleted_at', '=', null)
                                                       ->whereHasMorph('playlistable', Page::class, function($query){
                                                           $query->has('channel');
                                                       })
                                                        // where playlist is in this layer in the scheduler (for now during seeding, not in app)
                                                       ->whereHas('playlist', function($query) use($layer, $playlist_ids) {
                                                           $query->whereIn('id', $playlist_ids/*function($query) use($layer) {
                                                           $query->select('layerable_id')->from('schedule_layer_listings')->where('channel_layer_id', $layer->id)->where('layerable_type', Playlist::class);
                                                       }*);
                })
                                                       ->orderBy('playlist_id')
                                                       ->orderBy('sort_order')
                                                       ->get();


            $prev_playlist = null;
            foreach($schedulePlaylistListings as $schedulePlaylistListing){
                if(is_null($prev_playlist) || $prev_playlist->scheduleable_id !== $schedulePlaylistListing->playlist_id) {
                    // dump("Playlist \"".$schedulePlaylistListing->playlist->name.'"');
                    // add playlist to schedule listing
                    $scheduleListing = ScheduleListing::firstOrNew([
                        // make this playlist a child of the existing listing node
                        // (from the layer)
                        'parent_id'     => $parentNode->id,
                        'scheduleable_id'   => $schedulePlaylistListing->playlist_id,
                        'scheduleable_type' => Playlist::class,
                    ]);
                    if (isset($params['sort_order'])) {
                        $scheduleListing->moveToOrder($params['sort_order']);
                    } else {
                        $scheduleListing->setHighestOrderNumber();
                    }
                    try{
                        $scheduleListing->save();
                    } catch(\Exception $e){
                        dump('Save failed for: ', $scheduleListing);
                        Log::warning('Save failed for: ', $scheduleListing);
                    }
                    $prev_playlist = $scheduleListing;
                }

                // dump(" --> Page \"".$schedulePlaylistListing->playlistable->name.'"');

                // get true PlaylistListing id, not from the SchedulePlaylistListing
                $playlistListing = PlaylistListing::where('playlist_id', $schedulePlaylistListing->playlist_id)
                                                  ->where('playlistable_id', $schedulePlaylistListing->playlistable_id)
                                                  ->where('playlistable_type', $schedulePlaylistListing->playlistable_type)
                                                  ->first();

                // add page to schedule listing
                $scheduleListing = ScheduleListing::firstOrNew([
                    // make this page a child of the playlist node
                    // (from the layer)
                    'parent_id' => $prev_playlist->id,
                    'listing_id'   => $playlistListing->id,
                    'listing_type' => PlaylistListing::class,
                    'scheduleable_id' => $playlistListing->playlistable_id,
                    'scheduleable_type' => $playlistListing->playlistable_type,
                ]);

                if (isset($params['sort_order'])) {
                    $scheduleListing->moveToOrder($params['sort_order']);
                } else {
                    $scheduleListing->setHighestOrderNumber();
                }
                $scheduleListing->save();
            }*/
        } // foreach layer
    }
};
