<?php

namespace App\Api\V1\Docs\Strategies\QueryParameters;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromFormRequest as GetFromFormRequestBase;

class GetFromFormRequest extends GetFromFormRequestBase
{
    public ?ExtractedEndpointData $endpointData;

    /**
     * @link https://scribe.knuckles.wtf/laravel/advanced/plugins
     * @param  \Knuckles\Camel\Extraction\ExtractedEndpointData  $endpointData The endpoint we are currently processing.
     *   Contains details about httpMethods, controller, method, route, url, etc, as well as already extracted data.
     * @param  array  $routeRules Array of rules for the ruleset which this route belongs to.
     *
     * See the documentation linked above for more details about writing custom strategies.
     *
     * @return array|null
     */
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        $this->authenticateUser();

        return parent::__invoke($endpointData, $routeRules);
    }

    protected function authenticateUser()
    {
        $user = new User();
        $user->company_id = 0;

        Auth::guard()->setUser($user);
    }
}
