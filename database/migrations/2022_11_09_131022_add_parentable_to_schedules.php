<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('schedules', function (Blueprint $table) {
            // The link to the listing table entry that belongs to
            // the Schedule's Scheduleable's (Page's) specific parent (Playlist),
            // The same Page id can exist in the listing pivot multiple times, so
            // we need a way to determine which parent is relevant to a given play out event,
            // so that the sibling pages and their statuses can be checked against the page to be played
            $table->nullableMorphs('parentable');
        });
    }

    public function down()
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropMorphs('parentable');
        });
    }
};
