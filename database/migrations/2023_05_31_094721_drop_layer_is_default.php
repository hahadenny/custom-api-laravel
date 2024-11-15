<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() : void
    {
        Schema::table('channel_layers', function(Blueprint $table) {
            Schema::table('channel_layers', function (Blueprint $table) {
                $table->dropIndex(['is_default', 'channel_id']);
                $table->dropColumn('is_default');
            });
        });
    }

    public function down() : void
    {
        Schema::table('channel_layers', function(Blueprint $table) {
            Schema::table('channel_layers', function (Blueprint $table) {
                $table->boolean('is_default')->after('channel_id')->default(false);
                $table->index(['is_default', 'channel_id']);
            });
        });
    }
};
