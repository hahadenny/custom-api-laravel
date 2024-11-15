<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\Page\BatchDestroyRequest;
use App\Api\V1\Requests\Page\BatchDuplicateRequest;
use App\Api\V1\Requests\Page\BatchRestoreRequest;
use App\Api\V1\Requests\Page\BatchUpdateRequest;
use App\Api\V1\Requests\Page\GenerateUniqueNameRequest;
use App\Api\V1\Requests\Page\StoreRequest;
use App\Api\V1\Requests\Page\UpdateRequest;
use App\Api\V1\Resources\PageListingResource;
use App\Api\V1\Resources\PageResource;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Playlist;
use App\Services\PageService;
use App\Services\Schedule\Helpers\ScheduleOccurrenceFactory;
use App\Traits\Controllers\AuthorizesBatchRequests;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PlaylistPageController extends Controller
{
    use AuthorizesBatchRequests;

    public function __construct()
    {
        $this->middleware(['can:view,playlist']);
        $this->authorizeResource(Page::class, 'page');
    }

    /**
     * Display a listing of pages.
     *
     * @group Playlist Page
     *
     * @param \App\Models\Playlist      $playlist
     * @param \App\Services\PageService $pageService
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Playlist $playlist, PageService $pageService)
    {
        return PageListingResource::collection($pageService->listing($playlist));
    }

    /**
     * Store a newly created page.
     *
     * @group Playlist Page
     *
     * @param \App\Api\V1\Requests\Page\StoreRequest $request
     * @param \App\Models\Playlist                   $playlist
     * @param \App\Services\PageService              $pageService
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, Playlist $playlist, PageService $pageService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new PageResource($pageService->store($authUser, $playlist, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified page.
     *
     * @group Playlist Page
     *
     * @param \App\Models\Playlist $playlist
     * @param \App\Models\Page     $page
     *
     * @return \App\Api\V1\Resources\PageResource
     */
    public function show(Playlist $playlist, Page $page)
    {
        $playlist->pages()->findOrFail($page->id);
        $page->setParentModel($playlist);

        return new PageResource($page);
    }

    /**
     * Generate a unique page name.
     *
     * @group Playlist Page
     *
     * @param  \App\Api\V1\Requests\Page\GenerateUniqueNameRequest  $request
     * @param  \App\Models\Playlist  $playlist
     * @param  \App\Services\PageService  $pageService
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function generateUniqueName(GenerateUniqueNameRequest $request, Playlist $playlist, PageService $pageService)
    {
        $this->authorize('generateUniqueName', Page::class);
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        return response()->json($pageService->generateUniqueName($authUser, $playlist, $request->validated()));
    }

    /**
     * Update the specified page.
     *
     * @group Playlist Page
     *
     * @param \App\Api\V1\Requests\Page\UpdateRequest                  $request
     * @param \App\Models\Playlist                                     $playlist
     * @param \App\Models\Page                                         $page
     * @param \App\Services\PageService                                $pageService
     * @param \App\Services\Schedule\Helpers\ScheduleOccurrenceFactory $occurrenceFactory
     *
     * @return \App\Api\V1\Resources\PageResource
     */
    public function update(
        UpdateRequest $request,
        Playlist $playlist,
        Page $page,
        PageService $pageService,
        ScheduleOccurrenceFactory $occurrenceFactory
    ) {
        $playlist->pages()->findOrFail($page->id);
        return new PageResource($pageService->update($page, $playlist, $request->validated(), $occurrenceFactory));
    }

    /**
     * Update the specified pages.
     *
     * @group Playlist Page
     *
     * @param \App\Api\V1\Requests\Page\BatchUpdateRequest $request
     * @param \App\Models\Playlist                         $playlist
     * @param \App\Services\PageService                    $pageService
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function batchUpdate(BatchUpdateRequest $request, Playlist $playlist, PageService $pageService)
    {
        return PageResource::collection($pageService->batchUpdate($playlist, $request->validated()));
    }

    /**
     * Duplicate the specified pages.
     *
     * @group Playlist Page
     *
     * @param \App\Api\V1\Requests\Page\BatchDuplicateRequest $request
     * @param \App\Models\Playlist                            $playlist
     * @param \App\Services\PageService                       $pageService
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function batchDuplicate(BatchDuplicateRequest $request, Playlist $playlist, PageService $pageService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        return PageResource::collection($pageService->batchDuplicate($authUser, $playlist, $request->validated()));
    }

    /**
     * Remove the specified page.
     *
     * @group Playlist Page
     *
     * @param \App\Models\Playlist      $playlist
     * @param \App\Models\Page          $page
     * @param \App\Services\PageService $pageService
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Playlist $playlist, Page $page, PageService $pageService)
    {
        $playlist->pages()->findOrFail($page->id);
        $pageService->delete($page, $playlist);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Remove the specified pages.
     *
     * @group Playlist Page
     *
     * @param \App\Api\V1\Requests\Page\BatchDestroyRequest $request
     * @param \App\Models\Playlist                          $playlist
     * @param \App\Services\PageService                     $pageService
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDestroy(BatchDestroyRequest $request, Playlist $playlist, PageService $pageService)
    {
        $pageService->batchDelete($playlist, $request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Restore the specified pages.
     *
     * @group Playlist Page
     *
     * @param \App\Api\V1\Requests\Page\BatchRestoreRequest $request
     * @param \App\Models\Playlist                          $playlist
     * @param \App\Services\PageService                     $pageService
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchRestore(BatchRestoreRequest $request, Playlist $playlist, PageService $pageService)
    {
        $pageService->batchRestore($playlist, $request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
