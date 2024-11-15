<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UserGroupChangeFromManyToManyToOneToMany extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('user_user_group');

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('user_group_id')->nullable()->default(null)->after('company_id')
                ->constrained()->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['user_group_id']);
            $table->dropColumn(['user_group_id']);
        });

        Schema::create('user_user_group', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_group_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->unique(['user_id', 'user_group_id']);
        });
    }
}
