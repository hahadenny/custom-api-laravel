<?php

namespace App\Services\Monitoring;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OhDearService
{
    /** @const string */
    public const BASE_URL = 'https://ohdear.app/api';

    protected function getClient()
    {
        return Http::withToken(config('services.oh-dear.token'))
                   ->bodyFormat('json')
                   ->contentType('application/vnd.api+json');
    }

    protected function getMe() : Response
    {
        return $this->getClient()->get(self::BASE_URL . '/me');
    }

    public function getSites() : Response
    {
        return $this->getClient()->get(self::BASE_URL . '/sites');
    }

    public function getSite(int $site_id) : Response
    {
        return $this->getClient()->get(self::BASE_URL . '/sites/' . $site_id);
    }

    public function getSitesCount() : int|string
    {
        $response = $this->getSites()->json();

        return $response['error'] ?? count($response['data']);
    }

    public function getFirstTeamId() : int|string
    {
        $response = $this->getMe()->json();

        return $response['error'] ?? $response['teams'][0]['id'];
    }

    public function createSite($data = []) : Response
    {
        return $this->getClient()->post(self::BASE_URL . '/sites', $data);
    }
}
