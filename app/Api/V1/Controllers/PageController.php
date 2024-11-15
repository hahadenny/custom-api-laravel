<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\Page\BatchAttachRequest;
use App\Api\V1\Requests\Page\BatchDetachRequest;
use App\Api\V1\Requests\Page\StoreRequest;
use App\Api\V1\Requests\Page\UpdateRequest;
use App\Api\V1\Resources\PageListingResource;
use App\Api\V1\Resources\PageResource;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\PageService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller exclusively for Pages that do not belong to any playlists,
 * it is not for accessing Pages directly by ID
 */
class PageController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Page::class, 'page');
    }

    /**
     * Display a listing of pages.
     *
     * @group Company Page
     *
     * @param  \App\Services\PageService  $pageService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(PageService $pageService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        return PageListingResource::collection($pageService->companyPageListing($authUser));
    }

    /**
     * Store a newly created page.
     *
     * @group Company Page
     *
     * @param  \App\Api\V1\Requests\Page\StoreRequest  $request
     * @param  \App\Services\PageService  $pageService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, PageService $pageService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new PageResource($pageService->store($authUser, null, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified page.
     *
     * @group Company Page
     *
     * @param  \App\Models\Page  $page
     * @return \App\Api\V1\Resources\PageResource
     */
    public function show(Page $page)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        $authUser->company->withoutPlaylistPages()->findOrFail($page->id);
        return new PageResource($page);
    }

    /**
     * Update the specified page.
     *
     * @group Company Page
     *
     * @param  \App\Api\V1\Requests\Page\UpdateRequest  $request
     * @param  \App\Models\Page  $page
     * @param  \App\Services\PageService  $pageService
     * @return \App\Api\V1\Resources\PageResource
     */
    public function update(UpdateRequest $request, Page $page, PageService $pageService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        $authUser->company->withoutPlaylistPages()->findOrFail($page->id);
        return new PageResource($pageService->update($page, null, $request->validated()));
    }

    /**
     * Attach the specified pages to a playlist.
     *
     * @group Company Page
     *
     * @param  \App\Api\V1\Requests\Page\BatchAttachRequest  $request
     * @param  \App\Services\PageService  $pageService
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function batchAttach(BatchAttachRequest $request, PageService $pageService)
    {
        $this->authorize('batch-attach', Page::class);
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        $pageService->batchAttach($authUser, $request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Detach the specified pages from a playlist.
     *
     * @group Company Page
     *
     * @param  \App\Api\V1\Requests\Page\BatchDetachRequest  $request
     * @param  \App\Services\PageService  $pageService
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function batchDetach(BatchDetachRequest $request, PageService $pageService)
    {
        $this->authorize('batch-detach', Page::class);
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        $pageService->batchDetach($authUser, $request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Store a newly created reference to the specified page.
     *
     * @group Company Page
     *
     * @param  \App\Models\Page  $page
     * @param  \App\Services\PageService  $pageService
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function storeReference(Page $page, PageService $pageService)
    {
        $this->authorize('store-reference', $page);
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        $authUser->company->withoutPlaylistPages()->findOrFail($page->id);
        return (new PageResource($pageService->storeReference($authUser, $page)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Remove the specified page.
     *
     * @group Company Page
     *
     * @param  \App\Models\Page  $page
     * @param  \App\Services\PageService  $pageService
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Page $page, PageService $pageService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        $authUser->company->withoutPlaylistPages()->findOrFail($page->id);
        $pageService->delete($page, null);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function getD3SharedMedia($page_id, Page $page, PageService $pageService)
    {
        $p = $page::where('id', $page_id)->get()->first();
        return response()->json($p->getD3SharedMedia());
    }
}
