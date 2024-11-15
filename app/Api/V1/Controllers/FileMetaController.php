<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\FileMeta\StoreRequest;
use App\Api\V1\Requests\FileMeta\UpdateRequest;
use App\Api\V1\Resources\FileMetaResource;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessFileMetaJob;
use App\Models\MediaMeta;
use App\Services\FileMetaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;


class FileMetaController extends Controller
{
    public function __construct()
    {
        // TODO: ??
        // $this->authorizeResource(MediaMeta::class, 'meta');
    }

    /**
     * Display a listing of file meta.
     *
     * @group FileMeta
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request, FileMetaService $service)
    {
        return FileMetaResource::collection($service->listing(Auth::guard()->user(), $request->all()));
    }

    /**
     * Store a newly created file meta for company.
     *
     * @group FileMeta
     *
     * @param  StoreRequest  $request
     * @return \Illuminate\Http\JsonResponse
     *
    public function store(StoreRequest $request, MediaMeta $meta, FileMetaService $service)
    {
        return new FileMetaResource($service->store($meta, $request->validated()));
    }
    //*/

    /**
     * Update file meta for company.
     *
     * @group FileMeta
     *
     * @param  UpdateRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateRequest $request, MediaMeta $meta, FileMetaService $service)
    {
        if(empty($meta->id)){
            return response()->json(['message' => 'File not found'], Response::HTTP_NOT_FOUND);
        }
        return new FileMetaResource($service->update($meta, $request->validated()));
    }

    /**
     * Create or Update file meta for company.
     *
     * @group FileMeta
     *
     * @param  StoreRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upsert(Request $request)
    {
        ProcessFileMetaJob::dispatch(Auth::guard()->user(), $request->input('media'), 'POST');
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function bridgeDelete(Request $request)
    {
        ProcessFileMetaJob::dispatch(Auth::guard()->user(), $request->input('media'), 'DELETE');
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
