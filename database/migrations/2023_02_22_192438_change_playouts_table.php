<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('schedule_channel_playouts', function (Blueprint $table) {
            $table->dropMorphs('listing');
            $table->dropConstrainedForeignId('page_id');
            $table->dropConstrainedForeignId('channel_layer_id');
            $table->dropConstrainedForeignId('listing_channel_id');
            $table->foreignId('schedule_set_id')->after('playout_channel_id')->constrained()->restrictOnDelete();
            $table->foreignId('schedule_listing_id')->after('schedule_set_id')->constrained()->restrictOnDelete();
        });
    }

    public function down()
    {
        DB::table('schedule_channel_playouts')->truncate();

        Schema::table('schedule_channel_playouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('schedule_set_id');
            $table->dropConstrainedForeignId('schedule_listing_id');
            $table->morphs('listing');
            $table->foreignId('page_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('channel_layer_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('listing_channel_id')->constrained('channels')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }
};
