<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('schedule_occurrences', function (Blueprint $table) {
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::table('schedule_occurrences', function (Blueprint $table) {
            $table->dropIndex('status');
        });
    }
};
