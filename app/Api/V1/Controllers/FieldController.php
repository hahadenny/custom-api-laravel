<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\Field\StoreRequest;
use App\Api\V1\Requests\Field\UpdateRequest;
use App\Api\V1\Resources\FieldResource;
use App\Http\Controllers\Controller;
use App\Models\Field;
use App\Services\FieldService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class FieldController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Field::class, 'field');
    }

    /**
     * Display a listing of fields.
     *
     * @group Field
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return FieldResource::collection($authUser->company->fields()->orderByDesc('id')->paginate());
    }

    /**
     * Store a newly created field.
     *
     * @group Field
     *
     * @param  \App\Api\V1\Requests\Field\StoreRequest  $request
     * @param  \App\Services\FieldService  $fieldService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, FieldService $fieldService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new FieldResource($fieldService->store($authUser, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified field.
     *
     * @group Field
     *
     * @param  \App\Models\Field  $field
     * @return \App\Api\V1\Resources\FieldResource
     */
    public function show(Field $field)
    {
        return new FieldResource($field);
    }

    /**
     * Update the specified field.
     *
     * @group Field
     *
     * @param  \App\Api\V1\Requests\Field\UpdateRequest  $request
     * @param  \App\Models\Field  $field
     * @return \App\Api\V1\Resources\FieldResource
     */
    public function update(UpdateRequest $request, Field $field)
    {
        return new FieldResource(tap($field)->update($request->validated()));
    }

    /**
     * Remove the specified field.
     *
     * @group Field
     *
     * @param  \App\Models\Field  $field
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Field $field)
    {
        $field->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
