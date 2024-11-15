<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class NestedSetGroupsUserPlaylistTemplate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /** @see \Kalnoy\Nestedset\NestedSetServiceProvider for method nestedSet() */

        Schema::table('user_groups', function (Blueprint $table) {
            $table->after('name', function (Blueprint $table) {
                $table->nestedSet();
            });
        });

        Schema::table('playlist_groups', function (Blueprint $table) {
            $table->after('created_by', function (Blueprint $table) {
                $table->nestedSet();
            });
        });

        Schema::table('template_groups', function (Blueprint $table) {
            $table->after('created_by', function (Blueprint $table) {
                $table->nestedSet();
            });
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        /** @see \Kalnoy\Nestedset\NestedSetServiceProvider for method dropNestedSet() */

        Schema::table('user_groups', function (Blueprint $table) {
            $table->dropNestedSet();
        });

        Schema::table('playlist_groups', function (Blueprint $table) {
            $table->dropNestedSet();
        });

        Schema::table('template_groups', function (Blueprint $table) {
            $table->dropNestedSet();
        });
    }
}
