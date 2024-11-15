<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ItemAndGroupMorphToListableSortOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
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

        $this->copyDataToListable();

        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn(['sort_order']);
        });

        Schema::table('page_groups', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn(['sort_order']);
        });

        Schema::table('playlists', function (Blueprint $table) {
            $table->dropColumn(['sort_order']);
        });

        Schema::table('playlist_groups', function (Blueprint $table) {
            $table->dropColumn(['sort_order']);
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(['sort_order']);
        });

        Schema::table('channel_groups', function (Blueprint $table) {
            $table->dropColumn(['sort_order']);
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['sort_order']);
        });

        Schema::table('template_groups', function (Blueprint $table) {
            $table->dropColumn(['sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->unsignedBigInteger('sort_order')->index()->after('page_number');
        });

        Schema::table('page_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('sort_order')->index()->after('name');
        });

        Schema::table('playlists', function (Blueprint $table) {
            $table->unsignedBigInteger('sort_order')->after('type');
        });

        Schema::table('playlist_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('sort_order')->after('name');
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->unsignedBigInteger('sort_order')->after('addresses');
        });

        Schema::table('channel_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('sort_order')->after('name');
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->unsignedBigInteger('sort_order')->after('data');
        });

        Schema::table('template_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('sort_order')->after('name');
        });

        // Not all data can be rolled back.
        $this->copyDataFromListable();

        Schema::dropIfExists('listables');
    }

    private function copyDataToListable()
    {
        DB::transaction(function () {
            foreach (DB::table('pages')->orderBy('id')->cursor() as $page) {
                DB::table('listables')->insert([
                    'componentable_type' => 'App\\Models\\Playlist',
                    'componentable_id' => $page->playlist_id,
                    'listable_type' => 'App\\Models\\Page',
                    'listable_id' => $page->id,
                    'group_id' => $page->page_group_id,
                    'sort_order' => $page->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => is_null($page->deleted_at) ? null : now(),
                ]);
            }

            foreach (DB::table('page_groups')->orderBy('id')->cursor() as $group) {
                DB::table('listables')->insert([
                    'componentable_type' => 'App\\Models\\Playlist',
                    'componentable_id' => $group->playlist_id,
                    'listable_type' => 'App\\Models\\PageGroup',
                    'listable_id' => $group->id,
                    'group_id' => $group->parent_id,
                    'sort_order' => $group->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => is_null($group->deleted_at) ? null : now(),
                ]);
            }

            foreach (DB::table('playlists')->orderBy('id')->cursor() as $playlist) {
                DB::table('listables')->insert([
                    'componentable_type' => 'App\\Models\\Project',
                    'componentable_id' => $playlist->project_id,
                    'listable_type' => 'App\\Models\\Playlist',
                    'listable_id' => $playlist->id,
                    'group_id' => $playlist->playlist_group_id,
                    'sort_order' => $playlist->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => is_null($playlist->deleted_at) ? null : now(),
                ]);
            }

            foreach (DB::table('playlist_groups')->orderBy('id')->cursor() as $group) {
                DB::table('listables')->insert([
                    'componentable_type' => 'App\\Models\\Project',
                    'componentable_id' => $group->project_id,
                    'listable_type' => 'App\\Models\\PlaylistGroup',
                    'listable_id' => $group->id,
                    'group_id' => $group->parent_id,
                    'sort_order' => $group->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => is_null($group->deleted_at) ? null : now(),
                ]);
            }

            foreach (DB::table('channels')->orderBy('id')->cursor() as $channel) {
                DB::table('listables')->insert([
                    'componentable_type' => 'App\\Models\\Company',
                    'componentable_id' => $channel->company_id,
                    'listable_type' => 'App\\Models\\Channel',
                    'listable_id' => $channel->id,
                    'group_id' => $channel->channel_group_id,
                    'sort_order' => $channel->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => is_null($channel->deleted_at) ? null : now(),
                ]);
            }

            foreach (DB::table('channel_groups')->orderBy('id')->cursor() as $group) {
                DB::table('listables')->insert([
                    'componentable_type' => 'App\\Models\\Company',
                    'componentable_id' => $group->company_id,
                    'listable_type' => 'App\\Models\\ChannelGroup',
                    'listable_id' => $group->id,
                    'group_id' => $group->parent_id,
                    'sort_order' => $group->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => is_null($group->deleted_at) ? null : now(),
                ]);
            }

            foreach (DB::table('templates')->orderBy('id')->cursor() as $template) {
                DB::table('listables')->insert([
                    'componentable_type' => 'App\\Models\\Company',
                    'componentable_id' => $template->company_id,
                    'listable_type' => 'App\\Models\\Template',
                    'listable_id' => $template->id,
                    'group_id' => $template->template_group_id,
                    'sort_order' => $template->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => is_null($template->deleted_at) ? null : now(),
                ]);
            }

            foreach (DB::table('template_groups')->orderBy('id')->cursor() as $group) {
                DB::table('listables')->insert([
                    'componentable_type' => 'App\\Models\\Company',
                    'componentable_id' => $group->company_id,
                    'listable_type' => 'App\\Models\\TemplateGroup',
                    'listable_id' => $group->id,
                    'group_id' => $group->parent_id,
                    'sort_order' => $group->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => is_null($group->deleted_at) ? null : now(),
                ]);
            }
        });
    }

    private function copyDataFromListable()
    {
        DB::transaction(function () {
            foreach (DB::table('listables')->orderBy('id')->cursor() as $listable) {
                if ($listable->listable_type === 'App\\Models\\Page') {
                    if (DB::table('pages')->where('id', $listable->listable_id)->doesntExist()) {
                        continue;
                    }

                    DB::table('pages')->where('id', $listable->listable_id)->update([
                        'sort_order' => $listable->sort_order,
                    ]);
                } elseif ($listable->listable_type === 'App\\Models\\PageGroup') {
                    if (DB::table('page_groups')->where('id', $listable->listable_id)->doesntExist()) {
                        continue;
                    }

                    DB::table('page_groups')->where('id', $listable->listable_id)->update([
                        'sort_order' => $listable->sort_order,
                    ]);
                } elseif ($listable->listable_type === 'App\\Models\\Playlist') {
                    if (DB::table('playlists')->where('id', $listable->listable_id)->doesntExist()) {
                        continue;
                    }

                    DB::table('playlists')->where('id', $listable->listable_id)->update([
                        'sort_order' => $listable->sort_order,
                    ]);
                } elseif ($listable->listable_type === 'App\\Models\\PlaylistGroup') {
                    if (DB::table('playlist_groups')->where('id', $listable->listable_id)->doesntExist()) {
                        continue;
                    }

                    DB::table('playlist_groups')->where('id', $listable->listable_id)->update([
                        'sort_order' => $listable->sort_order,
                    ]);
                } elseif ($listable->listable_type === 'App\\Models\\Channel') {
                    if (DB::table('channels')->where('id', $listable->listable_id)->doesntExist()) {
                        continue;
                    }

                    DB::table('channels')->where('id', $listable->listable_id)->update([
                        'sort_order' => $listable->sort_order,
                    ]);
                } elseif ($listable->listable_type === 'App\\Models\\ChannelGroup') {
                    if (DB::table('channel_groups')->where('id', $listable->listable_id)->doesntExist()) {
                        continue;
                    }

                    DB::table('channel_groups')->where('id', $listable->listable_id)->update([
                        'sort_order' => $listable->sort_order,
                    ]);
                } elseif ($listable->listable_type === 'App\\Models\\Template') {
                    if (DB::table('templates')->where('id', $listable->listable_id)->doesntExist()) {
                        continue;
                    }

                    DB::table('templates')->where('id', $listable->listable_id)->update([
                        'sort_order' => $listable->sort_order,
                    ]);
                } elseif ($listable->listable_type === 'App\\Models\\TemplateGroup') {
                    if (DB::table('template_groups')->where('id', $listable->listable_id)->doesntExist()) {
                        continue;
                    }

                    DB::table('template_groups')->where('id', $listable->listable_id)->update([
                        'sort_order' => $listable->sort_order,
                    ]);
                }
            }
        });
    }
}
