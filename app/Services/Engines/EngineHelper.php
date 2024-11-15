<?php

namespace App\Services\Engines;

class EngineHelper
{
    protected const BRIDGE_URLS = [
        '/getSharePath',
        '/getShareFiles',
        '/getDownloadPercent',
        '/getFileSize',
        '/uploadMedia',
        '/uploadSharedMedia',
        // Should eventually be replaced with API calls to new project endpoints
        '/getProjectPath',
        '/getProjects',
    ];

    /**
     * extract file extension from a URL
     */
    public function getExtFromUrl(string $url) : string
    {
        $url_path = parse_url($url, PHP_URL_PATH);
        return mb_strtolower(pathinfo($url_path, PATHINFO_EXTENSION));
    }

    /**
     * Get the first asset matching the pattern
     */
    public function findAssetMatch($asset, string $pattern="/\/[^\/]*$/") : string
    {
        $assetMatches = [];
        preg_match($pattern, $asset, $assetMatches);
        return $assetMatches[0] ?? '';
    }

    /**
     * Convert request URLs to allow calls to different D3 API versions.
     */
    public function convertUrlPaths(string|array $url) : string|array
    {
        if(is_array($url)){
            foreach($url as $i => $path){
                $url[$i] = $this->convertUrlPaths($path);
            }
            return $url;
        }
        return $this->parseUrlPath($url);
    }

    /**
     * Qualify the URL for the request to allow calls to different D3 API versions.
     * (Legacy v1 requests from Porta don't have `api` version in their path)
     */
    protected function parseUrlPath(string $url): string
    {
        // don't parse bridge URLs
        $isBridgeUrl = in_array(strtolower($url), array_map('strtolower', self::BRIDGE_URLS));
        // if JSON, this entry in the url array is sending data; ignore it
        return ($this->isJson($url) || $isBridgeUrl || str_contains($url, 'api/')) ? $url : '/api/v1'.$url;
    }

    public function isJson($string) : bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
