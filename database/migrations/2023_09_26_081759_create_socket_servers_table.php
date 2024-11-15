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
        Schema::create('socket_servers', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->foreignId('cluster_id')
                ->constrained('clusters')
                ->cascadeOnDelete();
            $table->json('params')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('socket_servers');
    }
};
