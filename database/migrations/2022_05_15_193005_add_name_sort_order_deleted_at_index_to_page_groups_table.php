<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNameSortOrderDeletedAtIndexToPageGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('page_groups', function (Blueprint $table) {
            $table->index(['name']);
            $table->index(['sort_order']);
            $table->index(['deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('page_groups', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['sort_order']);
            $table->dropIndex(['deleted_at']);
        });
    }
}
