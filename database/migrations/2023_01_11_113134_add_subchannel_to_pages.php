<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('pages', function (Blueprint $table) {
            // if multiple are needed for a page, create a duplicate of the page
            $table->string('subchannel')->nullable()->comment('semi-temporary Avalanche channel');
        });
    }

    public function down()
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('subchannel');
        });
    }
};
