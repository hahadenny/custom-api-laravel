<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // This table has extra checks because the remote database definitions are
        // likely out of sync with migrations.
        // This happened because ...someone... may have made manual changes to those
        // databases "just to check something" and then didn't undo them.
        // I'm not saying who that person may be but they probably regret it and won't
        // do it again because this was a pain to set up.
        $layerTableName = 'channel_layers';
        Schema::table($layerTableName, function (Blueprint $table) use ($layerTableName) {
            // add sort order col and indexes to `channel_layers` if not exist
            $schemaManager = Schema::getConnection()->getDoctrineSchemaManager();
            $columns = $schemaManager->listTableColumns($layerTableName);
            $tableIndexes = $schemaManager->listTableIndexes($layerTableName);

            if (in_array('sort_order', array_keys($columns))) {
                // just drop everything if it exists so we don't have to worry about existing null entries
                if (array_key_exists("channel_layers_sort_order_index", $tableIndexes)) {
                    $table->dropIndex('channel_layers_sort_order_index');
                }
                if (array_key_exists("channel_layers_sort_order_channel_id_unique", $tableIndexes)) {
                    $table->dropUnique(['sort_order', 'channel_id']);
                }
                $table->dropColumn('sort_order');
            }
        });
        // previous function needs to finish before re-adding column
        Schema::table($layerTableName, function (Blueprint $table) {
            $table->unsignedBigInteger('sort_order')->index()->after('is_default');
        });

        // order for items within a layer
        // use this instead of channel_layer_listings
        Schema::create('schedule_layer_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_layer_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            // Automatically generated name is too long
            $table->morphs('layerable', 'schedule_layer_listings_l_type_l_id_index');
            $table->unsignedBigInteger('sort_order')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['channel_layer_id', 'layerable_type', 'layerable_id',],
                // Automatically generated name is too long
                'schedule_layer_listings_layer_id_l_type_l_id_unique'
            );
        });

        // order for items within a playlist
        Schema::create('schedule_playlist_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->morphs('playlistable', 'schedule_playlist_listings_playlist_id_sp_type_sp_id_index');
            $table->unsignedBigInteger('sort_order')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['playlist_id', 'playlistable_type', 'playlistable_id'],
                // Automatically generated name is too long
                'schedule_playlist_listings_playlist_id_p_type_p_id_unique'
            );
        });

        DB::transaction(function () {
            $this->copyPlaylistOrderFromListings();
            $this->copyLayerOrderFromListings();
        });

        Schema::dropIfExists('channel_layer_listings');
    }

    /**
     * Try to ensure that sort_order is unique
     */
    private function initLayerOrder()
    {
        $values = \App\Models\ChannelLayer::all();
        $upd_str = '';
        foreach($values as $i => $value){
            $order = $i+1;
            $upd_str .= " when `id` = {$value['id']} then {$order}
            ";
        }

        DB::update('UPDATE `channel_layers` SET `sort_order`= (case '.$upd_str.' end)');
    }

    /**
     * Copy listing order from playlist listings table into the new
     * scheduler playlist listing table so that we have some defaults
     *
     * @return void
     */
    private function copyPlaylistOrderFromListings()
    {
        DB::insert(
            'INSERT INTO `schedule_playlist_listings`
            SELECT
                NULL AS `id`,
                `playlist_id`,
                `playlistable_type`,
                `playlistable_id`,
                `sort_order`,
                NOW() AS `created_at`,
                NOW() AS `updated_at`,
                `deleted_at`
            FROM `playlist_listings`
            WHERE `playlist_listings`.`playlist_id` IS NOT NULL
              AND `playlist_listings`.`deleted_at` IS NULL
            ORDER BY `playlist_listings`.`id`'
        );
    }

    /**
     * Copy listing order from layer listings table into the new
     * scheduler layer listing table so that we have some defaults
     *
     * @return void
     */
    private function copyLayerOrderFromListings()
    {
        DB::insert(
            'INSERT INTO `schedule_layer_listings`
            SELECT
                NULL AS `id`,
                `channel_layer_id`,
                `layerable_type`,
                `layerable_id`,
                `sort_order`,
                NOW() AS `created_at`,
                NOW() AS `updated_at`,
                `deleted_at`
            FROM `channel_layer_listings`
            ORDER BY `id`'
        );
    }

    private function copyLayerOrderToListings()
    {
        DB::insert(
            'INSERT INTO `channel_layer_listings`
            SELECT
                NULL AS `id`,
                `channel_layer_id`,
                `layerable_type`,
                `layerable_id` ,
                `sort_order`,
                NOW() AS `created_at`,
                NOW() AS `updated_at`,
                `deleted_at`
            FROM `schedule_layer_listings`
            ORDER BY `id`'
        );
    }

    // -- DOWN --------------------------------------------------------------------------------------

    public function down()
    {
        Schema::table('channel_layers', function (Blueprint $table) {
            $table->dropIndex('channel_layers_sort_order_index');
            // make nullable instead of dropping in case some databases still expect the column to exist
            $table->unsignedBigInteger('sort_order')->nullable()->change();
        });

        // recreate channel_layer_listings
        Schema::create('channel_layer_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_layer_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->morphs('layerable');
            $table->unsignedBigInteger('sort_order');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['channel_layer_id', 'layerable_type', 'layerable_id'],
                // Automatically generated name is too long
                'schedule_layer_listing_layer_id_l_type_l_id_unique'
            );
        });
        $this->copyLayerOrderToListings();

        Schema::dropIfExists('schedule_channel_listings');
        Schema::dropIfExists('schedule_layer_listings');
        Schema::dropIfExists('schedule_playlist_listings');
    }
};
