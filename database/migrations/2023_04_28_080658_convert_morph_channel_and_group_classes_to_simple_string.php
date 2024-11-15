<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tableColumns = [
        'change_logs' => ['changeable_type'],
        'company_channel_listings' => ['companyable_type'],
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->tableColumns as $table => $columns) {
            foreach ($columns as $column) {
                DB::update('UPDATE `'.$table.'` SET `'.$column.'` = "channel" WHERE `'.$column.'` = "App\\\\Models\\\\Channel"');
                DB::update('UPDATE `'.$table.'` SET `'.$column.'` = "channel_group" WHERE `'.$column.'` = "App\\\\Models\\\\ChannelGroup"');
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        foreach ($this->tableColumns as $table => $columns) {
            foreach ($columns as $column) {
                DB::update('UPDATE `'.$table.'` SET `'.$column.'` = "App\\\\Models\\\\Channel" WHERE `'.$column.'` = "channel"');
                DB::update('UPDATE `'.$table.'` SET `'.$column.'` = "App\\\\Models\\\\ChannelGroup" WHERE `'.$column.'` = "channel_group"');
            }
        }
    }
};
