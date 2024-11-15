<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('origin')->after('scheduleable_id')->comment(
                "How the schedule was created; See \App\Enums\Schedule\Origin"
            );
            $table->dropUnique(['scheduleable_id', 'scheduleable_type']);
            // only one schedule per scheduleable and origin
            $table->unique(['scheduleable_id', 'scheduleable_type', 'origin']);
        });
    }

    public function down()
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('origin');
            $table->dropUnique(['scheduleable_id', 'scheduleable_type', 'origin']);
            // only one schedule per scheduleable
            $table->unique(['scheduleable_id', 'scheduleable_type']);
        });
    }
};
