<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration for the table used to tie rulesets to
 * a scheduleable entity (page, playlist, etc)
 *
 * Note: This doesn't truly need to exist,
 * but it works nicer conceptually
 */
return new class extends Migration {
    public function up()
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->morphs('scheduleable');
            $table->text('summary')->nullable();
            $table->text('rfc_string')->nullable();
            $table->string('ranges')->nullable();

            // TODO: look into this TZ nightmare further
            // @see https://en.wikipedia.org/wiki/List_of_tz_database_time_zones
            // @see https://www.php.net/manual/en/timezones.php
            $table->string('timezone')->default('UTC');
            $table->foreignId('created_by')
                  ->constrained('users')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();
            $table->timestamps();

            // only one schedule per scheduleable
            $table->unique(['scheduleable_id', 'scheduleable_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('schedules');
    }
};
