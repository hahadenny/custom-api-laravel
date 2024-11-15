<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeleteProjectPageListingsTablePagesInCompany extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('playlist_listings', function (Blueprint $table) {
            $table->foreignId('company_id')->after('id')
                ->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('playlist_id')->nullable()->change();
        });

        Schema::enableForeignKeyConstraints();

        $this->copyDataToPlaylistListings();

        Schema::dropIfExists('project_page_listings');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
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

        $this->moveDataFromPlaylistListings();

        Schema::table('playlist_listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->foreignId('playlist_id')->nullable(false)->change();
        });
    }

    private function copyDataToPlaylistListings()
    {
        DB::transaction(function () {
            Schema::disableForeignKeyConstraints();

            DB::insert(
                'INSERT INTO `playlist_listings`
                SELECT
                    NULL AS `id`,
                    0 AS `company_id`,
                    NULL AS `playlist_id`,
                    `projectable_type` AS `playlistable_type`,
                    `projectable_id` AS `playlistable_id`,
                    `group_id`, `sort_order`, `created_at`, `updated_at`, `deleted_at`
                FROM `project_page_listings`
                ORDER BY `id`'
            );

            Schema::enableForeignKeyConstraints();

            $cursor = DB::table('playlist_listings')
                ->orderBy('id')
                ->cursor();

            foreach ($cursor as $listing) {
                $companyId = null;

                if ($listing->playlistable_type === 'App\\Models\\Page') {
                    $companyId = DB::table('pages')
                        ->select(['company_id'])
                        ->where('id', $listing->playlistable_id)
                        ->value('company_id');
                } elseif ($listing->playlistable_type === 'App\\Models\\PageGroup') {
                    $companyId = DB::table('page_groups')
                        ->select(['company_id'])
                        ->where('id', $listing->playlistable_id)
                        ->value('company_id');
                }

                DB::table('playlist_listings')->where('id', $listing->id)->update([
                    'company_id' => $companyId,
                ]);
            }
        });
    }

    private function moveDataFromPlaylistListings()
    {
        DB::transaction(function () {
            $cursor = DB::table('playlist_listings')
                ->whereNull('playlist_id')
                ->orderBy('id')
                ->cursor();

            foreach ($cursor as $listing) {
                $defaultProjectId = DB::table('projects')
                    ->select(['id'])
                    ->where('company_id', $listing->company_id)
                    ->whereNull('deleted_at')
                    ->orderBy('id')
                    ->limit(1)
                    ->value('id');

                $projectId = DB::table('playlist_listings')
                    ->select('playlists.project_id')
                    ->join('playlists', 'playlist_listings.playlist_id', '=', 'playlists.id')
                    ->whereNotNull('playlist_listings.playlist_id')
                    ->where('playlist_listings.playlistable_type', $listing->playlistable_type)
                    ->where('playlist_listings.playlistable_id', $listing->playlistable_id)
                    ->orderBy('playlists.project_id')
                    ->limit(1)
                    ->value('playlists.project_id');

                DB::table('project_page_listings')->insert([
                    'project_id' => $projectId > 0 ? $projectId : $defaultProjectId,
                    'projectable_type' => $listing->playlistable_type,
                    'projectable_id' => $listing->playlistable_id,
                    'group_id' => $projectId > 0 ? $listing->group_id : null,
                    'sort_order' => $listing->sort_order,
                    'created_at' => $listing->created_at,
                    'updated_at' => $listing->updated_at,
                    'deleted_at' => $listing->deleted_at,
                ]);
            }

            DB::table('playlist_listings')->whereNull('playlist_id')->delete();
        });
    }
}
