<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() : void
    {
        Schema::table('channel_layers', function (Blueprint $table) {
            $table->foreignId('channel_id')->nullable()->default(null)->change();
        });
    }

    public function down() : void
    {
        Schema::table('channel_layers', function (Blueprint $table) {
            $table->foreignId('channel_id')->nullable(false)->change();
        });
    }
};
