<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration for the table that holds occurrences of the recurrence rules
 * for a scheduleable item (page, playlist, etc)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('schedule_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            // parsed rule datetime for this recurrence (end is inclusive)
            $table->dateTime('start');
            $table->dateTime('end')->nullable();
            // playing, paused, playing next...
            $table->string('status')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('schedule_occurrences');
    }
};
