<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class LinkPlaylistAndGroupToProject extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('playlists', function (Blueprint $table) {
            $table->foreignId('project_id')->after('company_id')
                ->constrained()->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::table('playlist_groups', function (Blueprint $table) {
            $table->foreignId('project_id')->after('company_id')
                ->constrained()->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('playlists', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn(['project_id']);
        });

        Schema::table('playlist_groups', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn(['project_id']);
        });
    }
}
