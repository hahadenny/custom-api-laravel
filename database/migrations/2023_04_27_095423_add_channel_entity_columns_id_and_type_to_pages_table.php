<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('channel_entity_type')->nullable()->after('channel_id');
            $table->unsignedBigInteger('channel_entity_id')->nullable()->after('channel_entity_type');
            $table->index(['channel_entity_type', 'channel_entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropMorphs('channel_entity');
        });
    }
};
