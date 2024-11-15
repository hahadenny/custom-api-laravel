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
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('cluster_id')
                ->nullable()
                ->after('ue_url')
                ->constrained('clusters')
                ->nullOnDelete();
            $table->dropColumn(['create_ue_server']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['cluster_id']);
            $table->dropColumn(['cluster_id']);
            $table->boolean('create_ue_server')->default(false);
        });
    }
};
