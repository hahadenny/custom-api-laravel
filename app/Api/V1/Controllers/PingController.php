<?php

namespace App\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\ClusterChecksService;
use Illuminate\Support\Facades\Redis;

class PingController extends Controller
{
    /**
     * Display a listing of fields.
     *
     * @group Field
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(ClusterChecksService $nodeService)
    {
        $redisReady = true;
        try {
            Redis::connection('default');
        } catch (\Exception $e) {
            $redisReady = $e->getMessage();
        }

        return response()->json([
            'redis'                     => $redisReady,
            ...$nodeService->getNodeInfo()
        ]);
    }


}
