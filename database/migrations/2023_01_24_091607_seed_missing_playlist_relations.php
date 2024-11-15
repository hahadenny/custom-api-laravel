<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // fix the schedule playlist relations
        // if a unique record already exists that would cause an error, do something trivial to skip it
        // NOTE: auto-inc will still be increased for the "failed" row
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
            ORDER BY `playlist_listings`.`sort_order`, `playlist_listings`.`id`
            ON DUPLICATE KEY UPDATE `schedule_playlist_listings`.id = `schedule_playlist_listings`.id
            '
        );
    }

    public function down()
    {
        // remove entries that were added after the fact
        // e.g., not created at (nearly) the same time within 3 secs
        DB::delete("
            DELETE FROM schedule_playlist_listings
            WHERE schedule_playlist_listings.id
                IN (
                    SELECT schedule_playlist_listings.`id`
                    FROM schedule_playlist_listings
                    RIGHT JOIN playlist_listings
                        ON schedule_playlist_listings.playlist_id = playlist_listings.playlist_id
                    WHERE (schedule_playlist_listings.`playlistable_type` = playlist_listings.playlistable_type
                        AND schedule_playlist_listings.`playlistable_id` = playlist_listings.playlistable_id)
                        AND ABS(TIME_TO_SEC(TIMEDIFF(schedule_playlist_listings.created_at, playlist_listings.created_at))) < 3
                )
        ");
    }
};
