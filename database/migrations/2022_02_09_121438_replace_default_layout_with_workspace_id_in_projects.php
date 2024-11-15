<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ReplaceDefaultLayoutWithWorkspaceIdInProjects extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('projects', function (Blueprint $table) {
            Schema::disableForeignKeyConstraints();

            $table->dropColumn('default_layout');
            $table->foreignId('workspace_id')->nullable()->after('company_id')
                ->constrained()->cascadeOnUpdate()->nullOnDelete();

            Schema::enableForeignKeyConstraints();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropColumn(['workspace_id']);
            $table->json('default_layout')->after('created_by');
        });
    }
}
