<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration for the rulesets for a schedule.
 * Includes the RFC string
 */
return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('schedule_rules', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_exclusion')->default(false)->comment(
                'This rule designates dates to be excluded from the schedule'
            );
            // dtstart
            $table->date('start_date')->nullable();
            $table->time('start_time')->nullable();
            // until
            $table->date('end_date')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('max_occurrences')->nullable()->comment('Number of times to recur');
            // byday / byweekday (array)
            $table->json('run_on_days')->nullable()->comment(
                'Weekdays to run on -- byday/byweekday (array of signed integers)'
            );
            // bymonth (array)
            $table->json('run_on_months')->nullable()->comment(
                'Months to run during -- bymonth (array of unsigned integers)'
            );
            $table->string('freq')->comment(
                'Recurrence frequency: [YEARLY, MONTHLY, WEEKLY, DAILY, HOURLY, MINUTELY, SECONDLY]'
            );
            $table->integer('interval')
                  ->default(1)
                  ->comment('The interval between each freq iteration');
            // REMINDER: \n or \r\n must exist between `DTSTART;` and other data if present
            $table->string('rfc_string')->comment(
                'The RFC-like string representation of the recurrence rule'
            );
            $table->string('summary')->nullable()->comment(
                'The human-readable string representation of the recurrence rule'
            );
            // this is a self-referencing key, need to create in 2 parts
            $table->unsignedBigInteger('recurring_rule_id')->nullable();
            $table->foreignId('schedule_id')
                  ->constrained()
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete();
            $table->foreignId('created_by')
                  ->constrained('users')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();
            $table->timestamps();
        });

        // Add self-reference
        Schema::table('schedule_rules', function (Blueprint $table) {
            $table->foreign('recurring_rule_id')
                  ->references('id')
                  ->on('schedule_rules')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('schedule_rules', function (Blueprint $table) {
            $table->dropForeign(['recurring_rule_id']);
        });
        Schema::dropIfExists('schedule_rules');
    }
};
