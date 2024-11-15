<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // order of layers within a channel
        Schema::table('channel_layers', function (Blueprint $table) {
            $table->unique(['sort_order', 'channel_id']);
        });
    }

    public function down()
    {
        Schema::table('channel_layers', function (Blueprint $table) {
            $table->dropUnique(['sort_order', 'channel_id']);
        });
    }
};
