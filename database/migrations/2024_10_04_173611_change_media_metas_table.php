<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() : void
    {
        DB::statement("TRUNCATE TABLE media_metas");

        Schema::table('media_metas', function(Blueprint $table) {
            $table->string('name')->after('data');
            $table->string('file_name')->after('name');
            $table->string('path')->after('file_name');
            $table->string('filepath')->after('path');
            $table->string('mime_type')->nullable()->after('filepath');
            $table->unsignedBigInteger('size')->after('mime_type');
            $table->text('thumbnail')->nullable()->after('size');
            $table->timestamp('file_last_updated_at')->nullable()->after('thumbnail')->comment('timestamp for the remote file itself, not the record');
            // don't cascade, we want both share and local data and their differences retained
            $table->foreignId('d3_local_media_metas_id')->nullable()->after('file_last_updated_at')->constrained('media_metas');
        });

        /*$metas = MediaMeta::all();
        // remember the old filepaths so we can check for duplicates
        $filepaths = [];
        foreach ($metas as $meta) {
            // convert the old `data[name]` field to the new `name`, `path` and `filepath` fields
            // Example -> "name":"__hdvg120hnuit_invite hap (objects/videofile/_tf1/jt/we/fresque totale/invite_we/__hdvg120hnuit_invite hap)"
            // Example -> "name":"Drapeau_grece.PNG (S:\\_Base - Media - PORTA\\_Intemporel\\TESTS)"

            $jsonName = $meta->data['name'];
            // ray($jsonName)->green()->label('$jsonName');
            $pieces = explode(' (', $jsonName);
            // ray($pieces)->label('data[name] pieces');


            if (!isset($pieces[0]) || !isset($pieces[1])) {
                // delete this record, we'll have no way to match it to the file sent by Porta Bridge
                // ray($jsonName)->orange()->label('Weird $jsonName');
                Log::warning("MediaMeta {$meta->id} was deleted: ".json_encode($meta->attributesToArray()));
                $meta->delete();
                continue;
            }
            $name = trim($pieces[0]);
            // ray($name)->blue()->label('trimmed $name');
            $filepath = trim(rtrim($pieces[1], ')'));
            // ray($filepath)->blue()->label('trimmed $filepath');
            $hasBackslash = mb_strpos($filepath, '\\') !== false;
            $separator = $hasBackslash ? '\\' : '/';

            $path_pieces = explode($separator, $filepath);
            $file = array_pop($path_pieces);

// ray($separator)->purple()->label('$separator');
// ray($path_pieces)->purple()->label('$path_pieces');
// ray($file)->purple()->label('popped $file last path segment');

            if($name === trim($file)) {
                // `path` has filename attached, so we should leave it off
                // ray('`path` has filename attached, so we should leave it off');
                $path = implode($separator, $path_pieces);
            } else {
                // last segment of path wasn't the file, add it back to path
                // ray('last segment of path wasn\'t the file, add it back to path');
                $path = implode($separator, $path_pieces) . $separator . $file;
                // full filepath needs name appended
                $filepath = $path . $separator . $name;
            }
            // ray($name)->green()->label('$name');
            // ray($path)->green()->label('$path');
            // ray($filepath)->green()->label('$filepath');

            $filepaths[$meta->id] = $filepath;

            $meta->name = $name;
            $meta->file_name = $name;
            $meta->path = $path;
            $meta->filepath = $filepath;
            $meta->data = json_encode([
                'description' => $meta->data['description'] ?? null,
                'tags' => array_unique(array_map('mb_strtolower', $meta->data['tags'])) ?? [],
            ], JSON_INVALID_UTF8_IGNORE|JSON_THROW_ON_ERROR);

            $meta->save();
        }

        // check for duplicates -- these will break the unique constraint
        $duplicates = $this->compare_arrays_by_value($filepaths, array_unique($filepaths));
        if(!empty($duplicates)) {
            Log::warning('MediaMeta duplicate(s) found: ' . implode(', ', $duplicates));
            MediaMeta::destroy(array_values($duplicates));
        }*/

        Schema::table('media_metas', function(Blueprint $table) {
            $table->unique('filepath');
        });
        ray()->stopShowingQueries();
    }

    // Get the differences where keys exist in both arrays but values are different
    protected function compare_arrays_by_value(array $array1, array $array2): array {
        $diff1 = array_diff_assoc($array1, $array2);
        $diff2 = array_diff_assoc($array2, $array1);

        // Combine the keys from both arrays and return unique keys
        return array_unique(array_merge(array_keys($diff1), array_keys($diff2)));
    }

    public function down() : void
    {
        Schema::table('media_metas', function(Blueprint $table) {
            $table->dropForeign(['d3_local_media_metas_id']);
            $table->dropUnique(['filepath']);
            $table->dropColumn('name');
            $table->dropColumn('file_name');
            $table->dropColumn('path');
            $table->dropColumn('filepath');
            $table->dropColumn('mime_type');
            $table->dropColumn('size');
            $table->dropColumn('thumbnail');
            $table->dropColumn('file_last_updated_at');
            $table->dropColumn('d3_local_media_metas_id');
        });
    }
};
