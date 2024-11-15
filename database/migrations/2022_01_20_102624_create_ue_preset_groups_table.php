<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUePresetGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /** @see \Kalnoy\Nestedset\NestedSetServiceProvider for method nestedSet() */

        Schema::create('ue_preset_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('ue_preset_asset_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 255);
            $table->unsignedBigInteger('sort_order');
            $table->nestedSet();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ue_preset_groups');
    }
}
