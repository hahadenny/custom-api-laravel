<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() : void
    {
        Schema::create('schedule_set_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('schedule_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(0);

            $table->timestamps();
        });
    }

    public function down() : void
    {
        Schema::dropIfExists('schedule_set_user');
    }
};
