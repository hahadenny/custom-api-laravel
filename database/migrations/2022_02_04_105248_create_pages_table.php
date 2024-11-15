<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('page_group_id')->nullable()->default(null)->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('template_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('playlist_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_live')->default(false);
            $table->unsignedBigInteger('page_number');
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
        Schema::dropIfExists('pages');
    }
}
