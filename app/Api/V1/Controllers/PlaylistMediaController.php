<?php

namespace App\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Playlist;

class PlaylistMediaController extends Controller
{
    public function __construct()
    {
        $this->middleware(['can:view,playlist']);
    }


    /**
     * Display a playlist media.
     *
     * @group Playlist
     * @param Playlist $playlist
     * @return mixed
     */
    public function index(Playlist $playlist)
    {
        return $playlist->pages()
            ->with(['channel:id,name'])
            ->where('has_media', true)
            ->get()
            ->map(function(Page $page) {
                return [
                    'id' => $page->id,
                    'name' => $page->name,
                    'channel' => $page->channel?->name,
                    'media' => $page->getMedia()
                ];
            })
            ->toArray();
    }
}
