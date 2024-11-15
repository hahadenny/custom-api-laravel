<?php

namespace App\Api\V1\Docs\Strategies\BodyParameters;

use App\Models\Channel;
use App\Models\ChannelGroup;
use App\Models\Company;
use App\Models\Page;
use App\Models\PageGroup;
use App\Models\Playlist;
use App\Models\PlaylistGroup;
use App\Models\Project;
use App\Models\Template;
use App\Models\TemplateGroup;
use App\Models\UePresetGroup;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Support\Facades\Auth;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromFormRequest as GetFromFormRequestBase;

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

    protected function getRouteValidationRules($formRequest)
    {
        $this->setRouteParameters($formRequest);

        return parent::getRouteValidationRules($formRequest);
    }

    protected function authenticateUser()
    {
        $user = new User();
        $user->company_id = 0;
        $user->setRelation('company', new Company());

        Auth::guard()->setUser($user);
    }

    protected function setRouteParameters($formRequest)
    {
        $route = $formRequest->route();
        $paths = explode('/', trim($route->uri(), '/'));

        if (in_array('{user}', $paths)) {
            $parameter = new User();
            $parameter->id = 0;

            $route->setParameter('user', $parameter);
        }
        if (in_array('{user_group}', $paths)) {
            $parameter = new UserGroup();
            $parameter->id = 0;

            $route->setParameter('user_group', $parameter);
        }
        if (in_array('{channel}', $paths)) {
            $parameter = new Channel();
            $parameter->channel_group_id = 0;

            $route->setParameter('channel', $parameter);
        }
        if (in_array('{channel_group}', $paths)) {
            $parameter = new ChannelGroup();
            $parameter->id = 0;

            $route->setParameter('channel_group', $parameter);
        }
        if (in_array('{playlist}', $paths)) {
            $parameter = new Playlist();
            $parameter->id = 0;
            $parameter->playlist_group_id = 0;
            $parameter->setRelation('project', new Project());

            $route->setParameter('playlist', $parameter);
        }
        if (in_array('{playlist_group}', $paths)) {
            $parameter = new PlaylistGroup();
            $parameter->id = 0;

            $route->setParameter('playlist_group', $parameter);
        }
        if (in_array('{template}', $paths)) {
            $parameter = new Template();
            $parameter->template_group_id = 0;

            $route->setParameter('template', $parameter);
        }
        if (in_array('{template_group}', $paths)) {
            $parameter = new TemplateGroup();
            $parameter->id = 0;

            $route->setParameter('template_group', $parameter);
        }
        if (in_array('{ue_preset_group}', $paths)) {
            $parameter = new UePresetGroup();
            $parameter->id = 0;

            $route->setParameter('ue_preset_group', $parameter);
        }
        if (in_array('{page}', $paths)) {
            $parameter = new Page();

            $route->setParameter('page', $parameter);
        }
        if (in_array('{page_group}', $paths)) {
            $parameter = new PageGroup();
            $parameter->id = 0;

            $route->setParameter('page_group', $parameter);
        }
        if (in_array('{project}', $paths)) {
            $parameter = new Project();
            $parameter->id = 0;

            $route->setParameter('project', $parameter);
        }

        $formRequest->setRouteResolver(function () use ($route) {
            return $route;
        });
    }
}
