<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // order of layers within a channel
        Schema::table('channel_layers', function (Blueprint $table) {
            $table->unsignedBigInteger('sort_order');
            $table->index('sort_order');
        });

        // pivot between a channel_layer and its children
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

        $this->copyPlaylistLayerRelation();

        // remove temporary non-listing relationships
        Schema::table('playlists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('channel_layer_id');
        });
        Schema::table('pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('schedule_playlist_id');
        });
    }

    /**
     * Copy layer id from playlists table into the new layer
     * listing table so that we don't lose data
     *
     * @return void
     */
    private function copyPlaylistLayerRelation()
    {
        DB::insert('INSERT INTO `channel_layer_listings`
                SELECT
                    NULL AS `id`,
                    `channel_layer_id`,
                    `projectable_type` AS `layerable_type`,
                    `projectable_id` AS `layerable_id`,
                    `sort_order`,
                    NOW() AS `created_at`,
                    NOW() AS `updated_at`,
                    NULL AS `deleted_at`
                FROM `project_listings`
                INNER JOIN `playlists`
                    ON `playlists`.`project_id` = `project_listings`.`project_id`
                    AND `projectable_type` = "App\\\\Models\\\\Playlist"
                WHERE `playlists`.`channel_layer_id` IS NOT NULL
                ORDER BY `project_listings`.`sort_order`'
        );
    }

    // --------------------------------------------------------------------------------------

    public function down()
    {
        Schema::table('channel_layers', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        // re-add channel layer to playlists
        Schema::table('playlists', function (Blueprint $table) {
            $table->foreignId('channel_layer_id')
                  ->nullable()
                  ->after('playlist_group_id')
                  ->constrained()
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();
        });

        // re-add schedule_playlist_id to pages
        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('schedule_playlist_id')
                  ->nullable()
                  ->after('channel_id')
                  ->constrained('playlists')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();
        });

        $this->restoreData();

        Schema::dropIfExists('channel_layer_listings');
    }

    private function restoreData()
    {
        DB::transaction(function () {
            $this->restorePlaylistLayerRelation();
            $this->restorePageSchedulePlaylistRelation();
        });
    }

    /**
     * Copy layer id from new layer listing table back into
     * the playlists table so that we don't lose data
     *
     * @return void
     */
    private function restorePlaylistLayerRelation()
    {
        DB::update('
            UPDATE `playlists`, `channel_layer_listings`
            SET playlists.channel_layer_id = channel_layer_listings.channel_layer_id,
                playlists.updated_at = NOW()
            WHERE playlists.id = channel_layer_listings.layerable_id
                AND channel_layer_listings.layerable_type = "App\\\\Models\\\\Playlist"'
        );
    }

    /**
     * Copy layer id from the new layer listing table back into
     * the pages table so that we don't lose data
     *
     * @return void
     */
    private function restorePageSchedulePlaylistRelation()
    {
        DB::update('
            UPDATE `pages`, `playlist_listings`
            SET `pages`.`schedule_playlist_id` = `playlist_listings`.`playlist_id`,
                `pages`.`updated_at` = NOW()
            WHERE `pages`.`id` = `playlist_listings`.`playlistable_id`
                AND `playlist_listings`.`playlistable_type` = "App\\\\Models\\\\Page"
        ');
    }
};
