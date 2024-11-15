<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class TerraformService
{
    const BASE_URL = 'https://app.terraform.io/api/v2';

    protected function getClient() : PendingRequest
    {
        return Http::withToken(config('services.terraform.token'))
            ->bodyFormat('json')
            ->contentType('application/vnd.api+json');
    }

    public function getRuns($workspace, $params = [])
    {
        return $this->getClient()->get(self::BASE_URL . '/workspaces/' . $workspace . '/runs', $params);
    }

    public function createRun($data = [])
    {
        return $this->getClient()->post(self::BASE_URL . '/runs', ['data' => $data]);
    }

    public function applyRun($runId)
    {
        return $this->getClient()->post(self::BASE_URL . '/runs/' . $runId . '/actions/apply');
    }

    public function getRun($runId)
    {
        return $this->getClient()->get(self::BASE_URL . '/runs/' . $runId);
    }
}
