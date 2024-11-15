<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\ImportTemplateRequest;
use App\Api\V1\Requests\Template\BatchDestroyRequest;
use App\Api\V1\Requests\Template\BatchDuplicateRequest;
use App\Api\V1\Requests\Template\BatchExportRequest;
use App\Api\V1\Requests\Template\BatchUpdateRequest;
use App\Api\V1\Requests\Template\StoreRequest;
use App\Api\V1\Requests\Template\UpdateRequest;
use App\Api\V1\Resources\TemplateResource;
use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Services\Exports\TemplateExportService;
use App\Services\Imports\TemplateImportService;
use App\Services\TemplateService;
use App\Traits\Controllers\AuthorizesBatchRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class TemplateController extends Controller
{
    use AuthorizesBatchRequests;

    public function __construct()
    {
        $authUser = Auth::guard()->user();

        //admin accounts for updating d3 templates
        $d3Accounts = config('services.d3.accounts');
        if ($d3Accounts) {
            $d3Emails = explode(',', $d3Accounts);
        }

        if ($authUser && ($d3Accounts && in_array($authUser->email, $d3Emails) || $authUser->isAdmin() || $authUser->isSuperAdmin())) {
            //no need to authorize
        } else {
            $this->authorizeResource(Template::class, 'template');
        }
    }

    /**
     * Display a listing of templates.
     *
     * @group Template
     *
     * @param  Request  $request
     * @param  \App\Services\TemplateService  $templateService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request, TemplateService $templateService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        return TemplateResource::collection($templateService->listing($authUser, $request->query()));
    }

    /**
     * Store a newly created template.
     *
     * @group Template
     *
     * @param  \App\Api\V1\Requests\Template\StoreRequest  $request
     * @param  \App\Services\TemplateService  $templateService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request, TemplateService $templateService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return (new TemplateResource($templateService->store($authUser, $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified template.
     *
     * @group Template
     *
     * @param  \App\Models\Template  $template
     * @return \App\Api\V1\Resources\TemplateResource
     */
    public function show(Template $template)
    {
        $template->loadMissing(['tags']);
        return new TemplateResource($template);
    }

    /**
     * Update the specified template.
     *
     * @group Template
     *
     * @param  \App\Api\V1\Requests\Template\UpdateRequest  $request
     * @param  \App\Models\Template  $template
     * @param  \App\Services\TemplateService  $templateService
     * @return \App\Api\V1\Resources\TemplateResource
     */
    public function update(UpdateRequest $request, Template $template, TemplateService $templateService)
    {
        return new TemplateResource($templateService->update($template, $request->validated()));
    }

    /**
     * Update the specified templates.
     *
     * @group Template
     *
     * @param  \App\Api\V1\Requests\Template\BatchUpdateRequest  $request
     * @param  \App\Services\TemplateService  $templateService
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function batchUpdate(BatchUpdateRequest $request, TemplateService $templateService)
    {
        return TemplateResource::collection($templateService->batchUpdate($request->validated()));
    }

    /**
     * Duplicate the specified templates.
     *
     * @group Template
     *
     * @param  \App\Api\V1\Requests\Template\BatchDuplicateRequest  $request
     * @param  \App\Services\TemplateService  $templateService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDuplicate(BatchDuplicateRequest $request, TemplateService $templateService)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        $templateService->batchDuplicate($authUser, $request->validated());

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Export the specified templates to a file.
     *
     * @group Template
     *
     * @param  \App\Api\V1\Requests\Template\BatchExportRequest  $request
     * @param  \App\Services\Exports\TemplateExportService  $templateExportService
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function batchExport(BatchExportRequest $request, TemplateExportService $templateExportService)
    {
        $this->authorize('batch-export', Template::class);
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();
        $data = $templateExportService->batchExport($authUser, $request->validated());

        return response()
            ->streamDownload(
                function () use ($data) {
                    echo $data;
                },
                $templateExportService->generateFileName($authUser->company),
                [
                    'Content-Type' => 'application/json',
                    'Access-Control-Expose-Headers' => 'Content-Disposition',
                ]
            );
    }

    /**
     * Import the templates from a file.
     *
     * @group Template
     *
     * @param  \App\Api\V1\Requests\ImportTemplateRequest  $request
     * @param  \App\Services\Imports\TemplateImportService  $templateImportService
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function import(ImportTemplateRequest $request, TemplateImportService $templateImportService)
    {
        $this->authorize('import', Template::class);
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        if ($request->has('file')) {
            $request->validateFileImportType(TemplateExportService::TYPE);
            /* @var UploadedFile $file */
            $file = $request->validated()['file'];
            $data = $templateImportService->getListingFromFile($file->getPathname());
            $data['file_path'] = $file->storeAs('template_import', time() . '.json', ['disk' => 'local']);
            return response()->json($data);
        }

        $request->validateDataImportType(TemplateExportService::TYPE);
        $params = $request->validated();
        $params['file_path'] = Storage::disk('local')->path($params['file_path']);
        $templateImportService->import($authUser, $params);
        Storage::disk('local')->delete($params['file_path']);
        return response()->json(null, Response::HTTP_CREATED);
    }

    /**
     * Remove the specified template.
     *
     * @group Template
     *
     * @param  \App\Models\Template  $template
     * @param  \App\Services\TemplateService  $templateService
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Template $template, TemplateService $templateService)
    {
        $templateService->delete($template);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Remove the specified templates.
     *
     * @group Template
     *
     * @param  \App\Api\V1\Requests\Template\BatchDestroyRequest  $request
     * @param  \App\Services\TemplateService  $templateService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDestroy(BatchDestroyRequest $request, TemplateService $templateService)
    {
        $templateService->batchDelete($request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Deactivate the specified templates.
     *
     * @group Template
     *
     * @param  \App\Api\V1\Requests\Template\BatchDestroyRequest  $request
     * @param  \App\Services\TemplateService  $templateService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDeactivate(BatchDestroyRequest $request, TemplateService $templateService)
    {
        $templateService->batchActivate($request->validated(), false);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Activate the specified templates.
     *
     * @group Template
     *
     * @param  \App\Api\V1\Requests\Template\BatchDestroyRequest  $request
     * @param  \App\Services\TemplateService  $templateService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchActivate(BatchDestroyRequest $request, TemplateService $templateService)
    {
        $templateService->batchActivate($request->validated(), true);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Restore the specified templates.
     *
     * @group Template
     *
     * @param  \App\Api\V1\Requests\Template\BatchDestroyRequest  $request
     * @param  \App\Services\TemplateService  $templateService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchRestore(BatchDestroyRequest $request, TemplateService $templateService)
    {
        $templateService->batchRestore($request->validated());
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Display a listing of template fields.
     *
     * @group Template
     *
     * @param  Request  $request
     * @param  \App\Services\TemplateService  $templateService
     * @return array
     */
    public function fields(Request $request, TemplateService $templateService)
    {
        /** @var \App\Models\Company $company */
        $company = Auth::guard()->user()->company;
        if (!$company) {
            return [];
        }
        return $templateService->getFields($company, $request->get('ids', []));
    }
}
