<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('schedule_playlist_id')
                  ->nullable()
                  ->after('channel_id')
                  ->constrained('playlists')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();
        });
    }

    public function down()
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('schedule_playlist_id');
        });
    }
};
