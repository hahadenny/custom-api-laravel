<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PageChannelFillChannelEntity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'page-channel:fill-channel-entity';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copy the value from channel_id to channel_entity_id if channel_entity_id is empty.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        DB::update('UPDATE `pages` SET `channel_entity_type` = "channel", `channel_entity_id` = `channel_id` WHERE `channel_id` IS NOT NULL AND `channel_entity_id` IS NULL');
        return Command::SUCCESS;
    }
}
