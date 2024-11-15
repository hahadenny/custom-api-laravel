<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\ImportFileJsonRequest;
use App\Api\V1\Requests\Playlist\BatchDestroyRequest;
use App\Api\V1\Requests\Playlist\BatchDuplicateRequest;
use App\Api\V1\Requests\Playlist\BatchExportRequest;
use App\Api\V1\Requests\Playlist\BatchUpdateRequest;
use App\Api\V1\Requests\Playlist\StoreRequest;
use App\Api\V1\Requests\Playlist\UpdateRequest;
use App\Api\V1\Resources\PlaylistResource;
use App\Http\Controllers\Controller;
use App\Models\Playlist;
use App\Models\Project;
use App\Services\Exports\PlaylistExportService;
use App\Services\Imports\PlaylistImportService;
use App\Services\PlaylistService;
use App\Traits\Controllers\AuthorizesBatchRequests;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ProjectPlaylistController extends Controller
{
    use AuthorizesBatchRequests;

    public function __construct()
    {
        $this->middleware(['can:view,project']);
        $this->authorizeResource(Playlist::class, 'playlist');
    }

    /**
     * Display a listing of playlists.
     *
     * @group Project Playlist
     *
     * @param  \App\Models\Project  $project
     * @param  \App\Services\PlaylistService  $playlistService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Project $project, PlaylistService $playlistService)
    {
        return PlaylistResource::collection($playlistService->listing($project));
    }

    /**
     * Store a newly created playlist.
     *
     * @group Project Playlist
     *
     * @param  \App\Api\V1\Requests\Playlist\StoreRequest  $request
     * @param  \App\Models\Project  $project
     * @param  \App\Services\PlaylistService  $playlistService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, Project $project, PlaylistService $playlistService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new PlaylistResource($playlistService->store($authUser, $project, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified playlist.
     *
     * @group Project Playlist
     *
     * @param  \App\Models\Project  $project
     * @param  \App\Models\Playlist  $playlist
     * @return \App\Api\V1\Resources\PlaylistResource
     */
    public function show(Project $project, Playlist $playlist)
    {
        $project->playlists()->findOrFail($playlist->id);
        return new PlaylistResource($playlist);
    }

    /**
     * Update the specified playlist.
     *
     * @group Project Playlist
     *
     * @param  \App\Api\V1\Requests\Playlist\UpdateRequest  $request
     * @param  \App\Models\Project  $project
     * @param  \App\Models\Playlist  $playlist
     * @param  \App\Services\PlaylistService  $playlistService
     * @return \App\Api\V1\Resources\PlaylistResource
     */
    public function update(UpdateRequest $request, Project $project, Playlist $playlist, PlaylistService $playlistService)
    {
        $project->playlists()->findOrFail($playlist->id);
        return new PlaylistResource($playlistService->update($playlist, $request->validated()));
    }

    /**
     * Update the specified playlists.
     *
     * @group Project Playlist
     *
     * @param  \App\Api\V1\Requests\Playlist\BatchUpdateRequest  $request
     * @param  \App\Models\Project  $project
     * @param  \App\Services\PlaylistService  $playlistService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function batchUpdate(BatchUpdateRequest $request, Project $project, PlaylistService $playlistService)
    {
        return PlaylistResource::collection($playlistService->batchUpdate($request->validated()));
    }

    /**
     * Duplicate the specified playlists.
     *
     * @group Project Playlist
     *
     * @param  \App\Api\V1\Requests\Playlist\BatchDuplicateRequest  $request
     * @param  \App\Models\Project  $project
     * @param  \App\Services\PlaylistService  $playlistService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDuplicate(BatchDuplicateRequest $request, Project $project, PlaylistService $playlistService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        $playlistService->batchDuplicate($authUser, $request->validated());

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Export the specified playlists to a file.
     *
     * @group Project Playlist
     *
     * @param  \App\Api\V1\Requests\Playlist\BatchExportRequest  $request
     * @param  \App\Models\Project  $project
     * @param  \App\Services\Exports\PlaylistExportService  $playlistExportService
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function batchExport(BatchExportRequest $request, Project $project, PlaylistExportService $playlistExportService)
    {
        $this->authorize('batch-export', Playlist::class);
        $data = $playlistExportService->batchExport($project, $request->validated());

        return response()
            ->streamDownload(
                function () use ($data) {
                    echo $data;
                },
                $playlistExportService->generateFileName($project),
                [
                    'Content-Type' => 'application/json',
                    'Access-Control-Expose-Headers' => 'Content-Disposition',
                ]
            );
    }

    /**
     * Import the playlists from a file.
     *
     * @group Project Playlist
     *
     * @param  \App\Api\V1\Requests\ImportFileJsonRequest  $request
     * @param  \App\Models\Project  $project
     * @param  \App\Services\Imports\PlaylistImportService  $playlistImportService
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function import(ImportFileJsonRequest $request, Project $project, PlaylistImportService $playlistImportService)
    {
        $this->authorize('import', Playlist::class);
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        if ($request->has('file')) {
            $request->validateFileImportType(PlaylistExportService::TYPE);
            $data = json_decode($request->validated()['file']->getContent(), true);

            return JsonResource::make($playlistImportService->listing($data));
        }

        $request->validateDataImportType(PlaylistExportService::TYPE);
        $playlistImportService->import($authUser, $project, $request->validated());

        return response()->json(null, Response::HTTP_CREATED);
    }

    /**
     * Remove the specified playlist.
     *
     * @group Project Playlist
     *
     * @param  \App\Models\Project  $project
     * @param  \App\Models\Playlist  $playlist
     * @param  \App\Services\PlaylistService  $playlistService
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Project $project, Playlist $playlist, PlaylistService $playlistService)
    {
        $project->playlists()->findOrFail($playlist->id);
        $playlistService->delete($playlist);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Remove the specified playlists.
     *
     * @group Project Playlist
     *
     * @param  \App\Api\V1\Requests\Playlist\BatchDestroyRequest  $request
     * @param  \App\Models\Project  $project
     * @param  \App\Services\PlaylistService  $playlistService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDestroy(BatchDestroyRequest $request, Project $project, PlaylistService $playlistService)
    {
        $playlistService->batchDelete($request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
