<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('channels', function (Blueprint $table) {
            // playing/paused/idle for loop mode
            $table->string('status')->nullable()->after('stream_url');
        });
    }

    public function down()
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
