<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeFormIoIdUePresetAssetIdTypeFieldsInTemplatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->string('type', 255)->after('name');
            $table->foreignId('ue_preset_asset_id')->nullable()->default(null)->change();
            $table->json('data')->after('type');
            $table->dropColumn(['form_io_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->foreignId('ue_preset_asset_id')->nullable(false)->change();
            $table->unsignedBigInteger('form_io_id')->after('name');
            $table->dropColumn(['data', 'type']);
        });
    }
}
