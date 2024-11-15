<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() : void
    {
        Schema::table('schedule_rules', function (Blueprint $table) {
            $table->boolean('all_day')->default(false)->after('end_time');
            $table->string('ends', 10)->nullable()->after('all_day');
            // RRULE RFC `BYMINUTE` (array)
            $table->json('run_on_minutes')->nullable()->after('max_occurrences');
            // RRULE RFC `BYHOUR` (array)
            $table->json('run_on_hours')->nullable()->after('run_on_minutes');
            // RRULE RFC `BYMONTHDAY` (array)
            $table->json('month_day')->nullable()->after('run_on_months');
            // RRULE RFC `UNTIL`
            $table->date('repeat_end_date')->nullable()->after('ends');
            $table->time('repeat_end_time')->nullable()->after('repeat_end_date');

            $table->dropConstrainedForeignId('recurring_rule_id');
        });
    }

    public function down() : void
    {
        Schema::table('schedule_rules', function (Blueprint $table) {

            // `recurring_rule_id` was never used, don't bother undoing

            $table->dropColumn('all_day');
            $table->dropColumn('ends');
            $table->dropColumn('run_on_minutes');
            $table->dropColumn('run_on_hours');
            $table->dropColumn('month_day');
            $table->dropColumn('repeat_end_date');
            $table->dropColumn('repeat_end_time');

        });
    }
};
