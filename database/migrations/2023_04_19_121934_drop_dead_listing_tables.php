<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() : void
    {
        Schema::dropIfExists('schedule_channel_listings');
        Schema::dropIfExists('schedule_layer_listings');
        Schema::dropIfExists('schedule_playlist_listings');
    }
};
