<?php

namespace App\Health;

use App\Services\Monitoring\ClusterChecksService;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 *
 */
class SlowQueriesCheck extends Check
{
    public const COMMAND_ALIAS = 'slow_queries';
    public const NAME = 'Slow Queries';
    protected int $errorThreshold = 1000; // milliseconds

    public function __construct(protected ClusterChecksService $nodeService)
    {
        parent::__construct();
    }

    public function run(): Result
    {
        $benchmark = $this->nodeService->benchmarkInMs();

        $result = Result::make()
                        ->meta([
                            'benchmark_in_ms' => $benchmark,
                        ])
                        ->shortSummary('Test query took '.$benchmark.'ms');

        return $benchmark >= $this->errorThreshold
            ? $result->failed("Queries too slow. Test query took " . ($benchmark / 1000) . " seconds.")
            : $result->ok();
    }
}
