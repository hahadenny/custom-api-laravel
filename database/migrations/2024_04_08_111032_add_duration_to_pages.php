<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() : void
    {
        Schema::table('pages', function(Blueprint $table) {
            $table->unsignedSmallInteger('duration')->nullable()->after('color');
        });
    }

    public function down() : void
    {
        Schema::table('pages', function(Blueprint $table) {
            $table->dropColumn(['duration']);
        });
    }
};
