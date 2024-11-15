<?php

use App\Models\Page;
use App\Models\Schedule\ScheduleListing;
use App\Models\Template;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() : void
    {
        // preserve the values that users customized
        $pagesWithCustomDefDuration = Page::with('template')
                                          ->whereNotNull('default_duration')
                                          ->whereNot('default_duration', ScheduleListing::DEFAULT_DURATION)
                                          ->get();
        Schema::table('templates', function(Blueprint $table) {
            $table->unsignedBigInteger('default_duration')->nullable()->after('data');
        });
        foreach($pagesWithCustomDefDuration as $page){
            Template::where('id', $page->template->id)
                    ->update(['default_duration' => $page->default_duration]);
        }
        Schema::table('pages', function(Blueprint $table) {
            $table->dropColumn('default_duration');
        });
    }

    public function down() : void
    {
        // preserve the values that users customized
        $templatesWithCustomDefDuration = Template::with('pages')
                                                  ->whereNotNull('default_duration')
                                                  ->whereNot('default_duration', ScheduleListing::DEFAULT_DURATION)
                                                  ->get();
        Schema::table('pages', function(Blueprint $table) {
            $table->unsignedBigInteger('default_duration')->nullable()->after('page_number');
        });
        foreach($templatesWithCustomDefDuration as $template){
            Page::whereIn('id', $template->pages->pluck('id'))
                ->update(['default_duration' => $template->default_duration]);
        }
        Schema::table('templates', function(Blueprint $table) {
            $table->dropColumn('default_duration');
        });
    }
};
