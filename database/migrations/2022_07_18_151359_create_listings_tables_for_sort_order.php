<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateListingsTablesForSortOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('playlist_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->morphs('playlistable');
            $table->foreignId('group_id')->nullable()->constrained('page_groups')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedBigInteger('sort_order');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['playlist_id', 'playlistable_type', 'playlistable_id'],
                // Automatically generated name gives an error: Identifier name '...' is too long.
                'playlist_listings_playlist_id_p_type_p_id_unique'
            );
        });

        Schema::create('project_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->morphs('projectable');
            $table->foreignId('group_id')->nullable()->constrained('playlist_groups')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedBigInteger('sort_order');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['project_id', 'projectable_type', 'projectable_id'],
                // Automatically generated name gives an error: Identifier name '...' is too long.
                'project_listings_project_id_p_type_p_id_unique'
            );
        });

        Schema::create('project_page_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->morphs('projectable');
            $table->foreignId('group_id')->nullable()->constrained('page_groups')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedBigInteger('sort_order');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['project_id', 'projectable_type', 'projectable_id'],
                // Automatically generated name gives an error: Identifier name '...' is too long.
                'project_page_listings_project_id_p_type_p_id_unique'
            );
        });

        Schema::create('company_template_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->morphs('companyable');
            $table->foreignId('group_id')->nullable()->constrained('template_groups')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedBigInteger('sort_order');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['company_id', 'companyable_type', 'companyable_id'],
                // Automatically generated name gives an error: Identifier name '...' is too long.
                'company_template_listings_company_id_c_type_c_id_unique'
            );
        });

        Schema::create('company_channel_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->morphs('companyable');
            $table->foreignId('group_id')->nullable()->constrained('channel_groups')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedBigInteger('sort_order');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['company_id', 'companyable_type', 'companyable_id'],
                // Automatically generated name gives an error: Identifier name '...' is too long.
                'company_channel_listings_company_id_c_type_c_id_unique'
            );
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('page_group_id');
            $table->dropConstrainedForeignId('playlist_id');
        });

        $this->copyDataToListings();

        Schema::dropIfExists('listables');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('page_group_id')->nullable()->default(null)->after('company_id')
                ->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('playlist_id')->after('template_id')
                ->constrained()->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::enableForeignKeyConstraints();

        Schema::create('listables', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('componentable');
            $table->morphs('listable');
            $table->foreignId('group_id')->nullable()->index();
            $table->unsignedBigInteger('sort_order')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['componentable_type', 'componentable_id', 'listable_type', 'listable_id'],
                // Automatically generated name gives an error: Identifier name '...' is too long.
                'listables_c_type_c_id_l_type_l_id_unique'
            );
        });

        // Not all data can be rolled back.
        $this->copyDataFromListings();

        Schema::dropIfExists('playlist_listings');
        Schema::dropIfExists('project_listings');
        Schema::dropIfExists('project_page_listings');
        Schema::dropIfExists('company_template_listings');
        Schema::dropIfExists('company_channel_listings');
    }

    private function copyDataToListings()
    {
        DB::transaction(function () {
            DB::insert(
                'INSERT INTO `playlist_listings`
                SELECT
                    NULL AS `id`,
                    `componentable_id` AS `playlist_id`,
                    `listable_type` AS `playlistable_type`,
                    `listable_id` AS `playlistable_id`,
                    `group_id`, `sort_order`, `created_at`, `updated_at`, `deleted_at`
                FROM `listables`
                WHERE `componentable_type` = "App\\\\Models\\\\Playlist"
                ORDER BY `id`'
            );

            DB::insert(
                'INSERT INTO `project_listings`
                SELECT
                    NULL AS `id`,
                    `componentable_id` AS `project_id`,
                    `listable_type` AS `projectable_type`,
                    `listable_id` AS `projectable_id`,
                    `group_id`, `sort_order`, `created_at`, `updated_at`, `deleted_at`
                FROM `listables`
                WHERE `componentable_type` = "App\\\\Models\\\\Project"
                    AND `componentable_id` > 0
                ORDER BY `id`'
            );

            DB::insert(
                'INSERT INTO `company_template_listings`
                SELECT
                    NULL AS `id`,
                    `componentable_id` AS `company_id`,
                    `listable_type` AS `companyable_type`,
                    `listable_id` AS `companyable_id`,
                    `group_id`, `sort_order`, `created_at`, `updated_at`, `deleted_at`
                FROM `listables`
                WHERE `componentable_type` = "App\\\\Models\\\\Company"
                    AND (
                        `listable_type` = "App\\\\Models\\\\Template"
                        OR `listable_type` = "App\\\\Models\\\\TemplateGroup"
                    )
                ORDER BY `id`'
            );

            DB::insert(
                'INSERT INTO `company_channel_listings`
                SELECT
                    NULL AS `id`,
                    `componentable_id` AS `company_id`,
                    `listable_type` AS `companyable_type`,
                    `listable_id` AS `companyable_id`,
                    `group_id`, `sort_order`, `created_at`, `updated_at`, `deleted_at`
                FROM `listables`
                WHERE `componentable_type` = "App\\\\Models\\\\Company"
                    AND (
                        `listable_type` = "App\\\\Models\\\\Channel"
                        OR `listable_type` = "App\\\\Models\\\\ChannelGroup"
                    )
                ORDER BY `id`'
            );
        });
    }

    private function copyDataFromListings()
    {
        DB::transaction(function () {
            $cursor = DB::table('playlist_listings')
                ->where('playlistable_type', 'App\\Models\\Page')
                ->orderBy('id')
                ->cursor();

            foreach ($cursor as $listing) {
                if (DB::table('pages')->where('id', $listing->playlistable_id)->doesntExist()) {
                    continue;
                }

                DB::table('pages')->where('id', $listing->playlistable_id)->update([
                    'page_group_id' => $listing->group_id,
                    'playlist_id' => $listing->playlist_id,
                ]);
            }

            DB::insert(
                'INSERT INTO `listables`
                SELECT
                    NULL AS `id`,
                    "App\\\\Models\\\\Playlist" AS `componentable_type`,
                    `playlist_id` AS `componentable_id`,
                    `playlistable_type` AS `listable_type`,
                    `playlistable_id` AS `listable_id`,
                    `group_id`, `sort_order`, `created_at`, `updated_at`, `deleted_at`
                FROM `playlist_listings`
                ORDER BY `id`'
            );

            DB::insert(
                'INSERT INTO `listables`
                SELECT
                    NULL AS `id`,
                    "App\\\\Models\\\\Project" AS `componentable_type`,
                    `project_id` AS `componentable_id`,
                    `projectable_type` AS `listable_type`,
                    `projectable_id` AS `listable_id`,
                    `group_id`, `sort_order`, `created_at`, `updated_at`, `deleted_at`
                FROM `project_listings`
                ORDER BY `id`'
            );

            DB::insert(
                'INSERT INTO `listables`
                SELECT
                    NULL AS `id`,
                    "App\\\\Models\\\\Company" AS `componentable_type`,
                    `company_id` AS `componentable_id`,
                    `companyable_type` AS `listable_type`,
                    `companyable_id` AS `listable_id`,
                    `group_id`, `sort_order`, `created_at`, `updated_at`, `deleted_at`
                FROM `company_template_listings`
                ORDER BY `id`'
            );

            DB::insert(
                'INSERT INTO `listables`
                SELECT
                    NULL AS `id`,
                    "App\\\\Models\\\\Company" AS `componentable_type`,
                    `company_id` AS `componentable_id`,
                    `companyable_type` AS `listable_type`,
                    `companyable_id` AS `listable_id`,
                    `group_id`, `sort_order`, `created_at`, `updated_at`, `deleted_at`
                FROM `company_channel_listings`
                ORDER BY `id`'
            );
        });
    }
}
