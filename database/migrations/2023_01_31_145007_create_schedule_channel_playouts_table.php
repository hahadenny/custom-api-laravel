<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('schedule_channel_playouts', function (Blueprint $table) {
            $table->id();

            // NOTE: must differentiate channels because (for now) grig wants pages to play out to their assigned channel
            // regardless of what is selected in the listing. But we need to know the channel to know
            // what is in the listing to be looped
            $table->foreignId('playout_channel_id')->constrained('channels')->cascadeOnUpdate()->cascadeOnDelete();
            // channel selected in the listing
            $table->foreignId('listing_channel_id')->constrained('channels')->cascadeOnUpdate()->cascadeOnDelete();
            // schedule_layer_listings or schedule_playlist_listing entry for this page
            $table->morphs('listing');
            // the item to play out -- this is NOT UNIQUE, it's just a shortcut
            $table->foreignId('page_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            // the layer ancestor -- this is NOT UNIQUE, it's just a shortcut
            $table->foreignId('channel_layer_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->dateTime('start')->nullable(); // won't have value when paused
            $table->dateTime('end')->nullable();
            // playing, paused, playing next...
            $table->string('status')->nullable();
            // elapsed and remaining set when paused
            $table->unsignedSmallInteger('elapsed')->nullable();
            $table->unsignedSmallInteger('remaining')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();

            $table->timestamps();
        });

        Schema::table('schedule_channel_playouts', function (Blueprint $table) {
            // self ref key
            $table->foreignId('next_id')->nullable()->after('listing_channel_id')
                  ->constrained('schedule_channel_playouts')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('schedule_channel_playouts');
    }
};
