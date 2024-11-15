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
        Schema::create('workflow_run_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('run_by_user_id')->nullable()->constrained('users')
                ->cascadeOnUpdate()->nullOnDelete();
            $table->string('workflow_type', 255);
            $table->json('workflow_data');
            $table->json('data');
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
        Schema::dropIfExists('workflow_run_logs');
    }
};
