<?php

namespace App\Services\Schedule;

use App\Jobs\SendSequencePlayoutJob;
use App\Models\Page;
use App\Models\PageGroup;
use App\Models\Playlist;
use App\Services\PageService;
use App\Services\PlaylistService;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PageGroupSequenceService
{
    use DispatchesJobs;

    public function __construct(
        protected ScheduleSetService $scheduleSetService,
        protected PlaylistService    $playlistService,
        protected PageService        $pageService
    ) {}

    /**
     * Play first page now, then delay the rest based on duration
     */
    public function play(Playlist $playlist, PageGroup $pageGroup) : void
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        $project = $authUser->userProject->project;
        $pages = $pageGroup->listingPages()->orderByPivot('sort_order')->get();

        /** @var Page $firstPage */
        $firstPage = $pages->shift();
        SendSequencePlayoutJob::dispatchSync($firstPage, ($firstPage->channel ?? $firstPage->channelEntity));

        $now = now();
        $delay = $firstPage->getDuration();
        $pages->each(function ($page, $i) use($now, &$delay) {
            $sendAt = $now->addSeconds($delay);

            Log::debug('-- DISPATCH "'.$page->name.'" AFTER '.$delay.' (send at '.$sendAt.')--');

            /** @var Page $page */
            SendSequencePlayoutJob::dispatch($page, ($page->channel ?? $page->channelEntity))->delay($delay);
            $delay += $page->getDuration();
        });
    }
}
