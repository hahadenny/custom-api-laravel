<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUePresetAssetsAndTemplateGroupsAndTemplatesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ue_preset_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 255);
            $table->string('ue_id', 255);
            $table->string('ue_project', 255);
            $table->string('ue_path', 255);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('template_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 255);
            $table->unsignedBigInteger('sort_order');
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('template_group_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('ue_preset_asset_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 255);
            $table->unsignedBigInteger('form_io_id');
            $table->unsignedBigInteger('sort_order');
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
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
        Schema::dropIfExists('templates');
        Schema::dropIfExists('template_groups');
        Schema::dropIfExists('ue_preset_assets');
    }
}
