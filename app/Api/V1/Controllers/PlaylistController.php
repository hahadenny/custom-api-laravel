<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Resources\PlaylistResource;
use App\Http\Controllers\Controller;
use App\Models\Playlist;
use App\Services\PlaylistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class PlaylistController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Playlist::class, 'playlist');
    }


    /**
     * Display a playlists.
     *
     * @group Playlist
     *
     * @param Request $request
     * @param PlaylistService $service
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request, PlaylistService $service)
    {
        return PlaylistResource::collection($service->plainListing(Auth::user(), $request->all()));
    }
}
