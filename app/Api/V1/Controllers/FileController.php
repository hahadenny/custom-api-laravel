<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\File\StoreRequest;
use App\Api\V1\Requests\File\UpdateRequest;
use App\Api\V1\Resources\FileResource;
use App\Http\Controllers\Controller;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class FileController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Media::class, 'file');
    }

    /**
     * Display a listing of workspaces.
     *
     * @group File
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request, FileService $service)
    {
        return FileResource::collection($service->listing(Auth::guard()->user(), $request->all()));
    }

    /**
     * Store a newly created file for company.
     *
     * @group File
     *
     * @param  StoreRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, FileService $service)
    {
        return new FileResource($service->store(Auth::guard()->user(), ['file' => $request->file('file')]));
    }

    /**
     * Update a file for company.
     *
     * @group File
     *
     * @param  UpdateRequest  $request
     * @param  Media  $file
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateRequest $request, Media $file, FileService $service)
    {
        return new FileResource($service->update($file, $request->validated()));
    }

    /**
     * Remove the specified workspace.
     *
     * @group File
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDestroy(Request $request, FileService $service)
    {
        $data = $request->validate(['ids' => 'required|array']);
        $service->destroy(Auth::user(), $data['ids']);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
    
    public function getFile(Request $request, $id, FileService $service)
    {        
        return response()->json($service->getFile(Auth::user(), $id));
    }
}
