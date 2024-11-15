<?php

namespace App\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Cluster;
use Illuminate\Http\Resources\Json\JsonResource;

class ClusterController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Cluster::class, 'cluster');
    }

    /**
     * Display a listing of clusters.
     *
     * @group Cluster
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        return JsonResource::collection(
            Cluster::query()->select(['id', 'region', 'name', 'settings'])->active()->orderBy('id')->paginate(1000)
        );
    }

}
