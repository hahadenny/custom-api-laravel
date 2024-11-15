<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('channel_layers', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }

    public function down()
    {
        Schema::table('channel_layers', function (Blueprint $table) {
            $table->unsignedBigInteger('sort_order');
        });
    }
};
