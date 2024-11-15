<?php

use Knuckles\Scribe\Extracting\Strategies;

return [

    'theme' => 'default',

    /*
     * The HTML <title> for the generated documentation. If this is empty, Scribe will infer it from config('app.name').
     */
    'title' => null,

    /*
     * A short description of your API. Will be included in the docs webpage, Postman collection and OpenAPI spec.
     */
    'description' => '',

    /*
     * The base URL displayed in the docs. If this is empty, Scribe will use the value of config('app.url').
     */
    'base_url' => null,

    /*
     * Tell Scribe what routes to generate documentation for.
     * Each group contains rules defining which routes should be included ('match', 'include' and 'exclude' sections)
     * and settings which should be applied to them ('apply' section).
     */
    'routes' => [
        [
            /*
             * Specify conditions to determine what routes will be a part of this group.
             * A route must fulfill ALL conditions to be included.
             */
            'match' => [
                /*
                 * Match only routes whose paths match this pattern (use * as a wildcard to match any characters). Example: 'users/*'.
                 */
                'prefixes' => ['api/*'],

                /*
                 * Match only routes whose domains match this pattern (use * as a wildcard to match any characters). Example: 'api.*'.
                 */
                'domains' => ['*'],

                /*
                 * [Dingo router only] Match only routes registered under this version. Wildcards are not supported.
                 */
                'versions' => ['v1'],
            ],

            /*
             * Include these routes even if they did not match the rules above.
             * The route can be referenced by name or path here. Wildcards are supported.
             */
            'include' => [
                // 'users.index', 'healthcheck*'
            ],

            /*
             * Exclude these routes even if they matched the rules above.
             * The route can be referenced by name or path here. Wildcards are supported.
             */
            'exclude' => [
                // '/health', 'admin.*'
            ],

            /*
             * Settings to be applied to all the matched routes in this group when generating documentation
             */
            'apply' => [
                /*
                 * Additional headers to be added to the example requests
                 */
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],

                /*
                 * If no @response or @transformer declarations are found for the route,
                 * Scribe will try to get a sample response by attempting an API call.
                 * Configure the settings for the API call here.
                 */
                'response_calls' => [
                    /*
                     * API calls will be made only for routes in this group matching these HTTP methods (GET, POST, etc).
                     * List the methods here or use '*' to mean all methods. Leave empty to disable API calls.
                     */
                    'methods' => ['GET'],

                    /*
                     * Laravel config variables which should be set for the API call.
                     * This is a good place to ensure that notifications, emails and other external services
                     * are not triggered during the documentation API calls.
                     * You can also create a `.env.docs` file and run the generate command with `--env docs`.
                     */
                    'config' => [
                        'app.env' => 'documentation',
                        // 'app.debug' => false,
                    ],

                    /*
                     * Query parameters which should be sent with the API call.
                     */
                    'queryParams' => [
                        // 'key' => 'value',
                    ],

                    /*
                     * Body parameters which should be sent with the API call.
                     */
                    'bodyParams' => [
                        // 'key' => 'value',
                    ],

                    /*
                     * Files which should be sent with the API call.
                     * Each value should be a valid path (absolute or relative to your project directory) to a file on this machine (but not in the project root).
                     */
                    'fileParams' => [
                        // 'key' => 'storage/app/image.png',
                    ],

                    /*
                     * Cookies which should be sent with the API call.
                     */
                    'cookies' => [
                        // 'name' => 'value'
                    ],
                ],
            ],
        ],
    ],

    /*
     * The type of documentation output to generate.
     * - "static" will generate a static HTMl page in the /public/docs folder,
     * - "laravel" will generate the documentation as a Blade view, so you can add routing and authentication.
     */
    'type' => 'static',

    /*
     * Settings for `static` type output.
     */
    'static' => [
        /*
         * HTML documentation, assets and Postman collection will be generated to this folder.
         * Source Markdown will still be in resources/docs.
         */
        'output_path' => 'public/docs',
    ],

    /*
     * Settings for `laravel` type output.
     */
    'laravel' => [
        /*
         * Whether to automatically create a docs endpoint for you to view your generated docs.
         * If this is false, you can still set up routing manually.
         */
        'add_routes' => true,

        /*
         * URL path to use for the docs endpoint (if `add_routes` is true).
         * By default, `/docs` opens the HTML page, `/docs.postman` opens the Postman collection, and `/docs.openapi` the OpenAPI spec.
         */
        'docs_url' => '/docs',

        /*
         * Middleware to attach to the docs endpoint (if `add_routes` is true).
         */
        'middleware' => [],
        /*
         * Directory within `public` in which to store CSS and JS assets.
         * By default, assets are stored in `public/vendor/scribe`.
         * If set, assets will be stored in `public/{{assets_directory}}`
         */
        'assets_directory' => null,
    ],

    'try_it_out' => [
        /**
         * Add a Try It Out button to your endpoints so consumers can test endpoints right from their browser.
         * Don't forget to enable CORS headers for your endpoints.
         */
        'enabled' => true,

        /**
         * The base URL for the API tester to use (for example, you can set this to your staging URL).
         * Leave as null to use the current app URL (config(app.url)).
         */
        'base_url' => null,

        /**
         * Fetch a CSRF token before each request, and add it as an X-XSRF-TOKEN header. Needed if you're using Laravel Sanctum.
         */
        'use_csrf' => false,

        /**
         * The URL to fetch the CSRF token from (if `use_csrf` is true).
         */
        'csrf_url' => '/sanctum/csrf-cookie',
    ],

    /*
     * How is your API authenticated? This information will be used in the displayed docs, generated examples and response calls.
     */
    'auth' => [
        /*
         * Set this to true if any endpoints in your API use authentication.
         */
        'enabled' => true,

        /*
         * Set this to true if your API should be authenticated by default. If so, you must also set `enabled` (above) to true.
         * You can then use @unauthenticated or @authenticated on individual endpoints to change their status from the default.
         */
        'default' => true,

        /*
         * Where is the auth value meant to be sent in a request?
         * Options: query, body, basic, bearer, header (for custom header)
         */
        'in' => 'bearer',

        /*
         * The name of the auth parameter (eg token, key, apiKey) or header (eg Authorization, Api-Key).
         */
        'name' => 'key',

        /*
         * The value of the parameter to be used by Scribe to authenticate response calls.
         * This will NOT be included in the generated documentation.
         * If this value is empty, Scribe will use a random value.
         */
        'use_value' => env('SCRIBE_AUTH_KEY'),

        /*
         * Placeholder your users will see for the auth parameter in the example requests.
         * Set this to null if you want Scribe to use a random value as placeholder instead.
         */
        'placeholder' => '{YOUR_AUTH_KEY}',

        /*
         * Any extra authentication-related info for your users. For instance, you can describe how to find or generate their auth credentials.
         * Markdown and HTML are supported.
         */
        'extra_info' => 'You can retrieve your token by visiting your dashboard and clicking <b>Generate API token</b>.',
    ],

    /*
     * Text to place in the "Introduction" section, right after the `description`. Markdown and HTML are supported.
     */
    'intro_text' => <<<INTRO
This documentation aims to provide all the information you need to work with our API.

<aside>As you scroll, you'll see code examples for working with the API in different programming languages in the dark area to the right (or as part of the content on mobile).
You can switch the language used with the tabs at the top right (or from the nav menu at the top left on mobile).</aside>
INTRO
    ,

    /*
     * Example requests for each endpoint will be shown in each of these languages.
     * Supported options are: bash, javascript, php, python
     * To add a language of your own, see https://scribe.knuckles.wtf/laravel/advanced/example-requests
     *
     */
    'example_languages' => [
        'bash',
        'javascript',
    ],

    /*
     * Generate a Postman collection (v2.1.0) in addition to HTML docs.
     * For 'static' docs, the collection will be generated to public/docs/collection.json.
     * For 'laravel' docs, it will be generated to storage/app/scribe/collection.json.
     * Setting `laravel.add_routes` to true (above) will also add a route for the collection.
     */
    'postman' => [
        'enabled' => true,

        /*
         * Manually override some generated content in the spec. Dot notation is supported.
         */
        'overrides' => [
            // 'info.version' => '2.0.0',
        ],
    ],

    /*
     * Generate an OpenAPI spec (v3.0.1) in addition to docs webpage.
     * For 'static' docs, the collection will be generated to public/docs/openapi.yaml.
     * For 'laravel' docs, it will be generated to storage/app/scribe/openapi.yaml.
     * Setting `laravel.add_routes` to true (above) will also add a route for the spec.
     */
    'openapi' => [
        'enabled' => true,

        /*
         * Manually override some generated content in the spec. Dot notation is supported.
         */
        'overrides' => [
            // 'info.version' => '2.0.0',
        ],
    ],

    /*
     * Custom logo path. This will be used as the value of the src attribute for the <img> tag,
     * so make sure it points to an accessible URL or path. Set to false to not use a logo.
     *
     * For example, if your logo is in public/img:
     * - 'logo' => '../img/logo.png' // for `static` type (output folder is public/docs)
     * - 'logo' => 'img/logo.png' // for `laravel` type
     *
     */
    'logo' => false,

    /**
     * The strategies Scribe will use to extract information about your routes at each stage.
     * If you create or install a custom strategy, add it here.
     */
    'strategies' => [
        'metadata' => [
            Strategies\Metadata\GetFromDocBlocks::class,
        ],
        'urlParameters' => [
            Strategies\UrlParameters\GetFromLaravelAPI::class,
            Strategies\UrlParameters\GetFromLumenAPI::class,
            Strategies\UrlParameters\GetFromUrlParamTag::class,
        ],
        'queryParameters' => [
            \App\Api\V1\Docs\Strategies\QueryParameters\GetFromFormRequest::class,
            Strategies\QueryParameters\GetFromInlineValidator::class,
            Strategies\QueryParameters\GetFromQueryParamTag::class,
        ],
        'headers' => [
            Strategies\Headers\GetFromRouteRules::class,
            Strategies\Headers\GetFromHeaderTag::class,
        ],
        'bodyParameters' => [
            \App\Api\V1\Docs\Strategies\BodyParameters\GetFromFormRequest::class,
            Strategies\BodyParameters\GetFromInlineValidator::class,
            Strategies\BodyParameters\GetFromBodyParamTag::class,
        ],
        'responses' => [
            Strategies\Responses\UseTransformerTags::class,
            Strategies\Responses\UseApiResourceTags::class,
            Strategies\Responses\UseResponseTag::class,
            Strategies\Responses\UseResponseFileTag::class,
            Strategies\Responses\ResponseCalls::class,
        ],
        'responseFields' => [
            Strategies\ResponseFields\GetFromResponseFieldTag::class,
        ],
    ],

    'fractal' => [
        /* If you are using a custom serializer with league/fractal, you can specify it here.
         * Leave as null to use no serializer or return simple JSON.
         */
        'serializer' => null,
    ],

    /*
     * [Advanced] Custom implementation of RouteMatcherInterface to customise how routes are matched
     *
     */
    'routeMatcher' => \Knuckles\Scribe\Matching\RouteMatcher::class,

    /**
     * For response calls, API resource responses and transformer responses,
     * Scribe will try to start database transactions, so no changes are persisted to your database.
     * Tell Scribe which connections should be transacted here.
     * If you only use one db connection, you can leave this as is.
     */
    'database_connections_to_transact' => [config('database.default')],
    'groups' => [
        /*
         * Endpoints which don't have a @group will be placed in this default group.
         */
        'default' => 'Endpoints',
        /*
         * By default, Scribe will sort groups alphabetically, and endpoints in the order their routes are defined.
         * You can override this by listing the groups, subgroups and endpoints here in the order you want them.
         *
         * Any groups, subgroups or endpoints you don't list here will be added as usual after the ones here.
         * If an endpoint/subgroup is listed under a group it doesn't belong in, it will be ignored.
         * Note: you must include the initial '/' when writing an endpoint.
         */
        'order' => [
            'Template' => [
                'Update the specified templates.',
                'Duplicate the specified templates.',
                'Remove the specified templates.',
                'Display a listing of templates.',
                'Store a newly created template.',
                'Display the specified template.',
                'Update the specified template.',
                'Remove the specified template.',
            ],
            'Endpoints' => [
                'GET /api/refresh',
                'Display a listing of schedules for a given channel',
                'Show all rrules for a given page',
                'Store a newly created rrule for a page',
                'Display the specified rrule',
                'Update the specified resource in storage.',
                'Remove the specified resource from storage.',
                'PUT /api/playlists/{playlist}/rrules/batch',
                'Show all rrules for a given playlist',
                'Store a newly created rrule for a playlist',
                'Display the specified rrule',
                'Update the specified resource in storage.',
                'Remove the specified resource from storage.',
                'Show all schedules for a given playlist',
                'Store a newly created schedule for a playlist',
                'Display the specified schedule',
                'Update the specified resource in storage.',
                'Remove the specified resource from storage.',
                'Show all schedules for a given page',
                'Store a newly created schedule for a page',
                'Display the specified schedule',
                'Update the specified resource in storage.',
                'Remove the specified resource from storage.',
            ],
            'User' => [
                'Get the authenticated User',
                'Update the specified users.',
                'Remove the specified users.',
                'Display a listing of users.',
                'Store a newly created user.',
                'Display the specified user.',
                'Update the specified user.',
                'Remove the specified user.',
            ],
            'Playlist Page' => [
                'Update the specified pages.',
                'Duplicate the specified pages.',
                'Remove the specified pages.',
                'Restore the specified pages.',
                'Display a listing of pages.',
                'Store a newly created page.',
                'Display the specified page.',
                'Update the specified page.',
                'Remove the specified page.',
            ],
            'Playlist' => [
                'Display a playlist media.',
                'Display a playlists.',
            ],
            'Workspace' => [
                'Display a listing of workspaces.',
                'Store a newly created workspace.',
                'Display the specified workspace.',
                'Update the specified workspace.',
                'Remove the specified workspace.',
            ],
            'Channel Group' => [
                'Update the specified channel groups.',
                'Duplicate the specified channel groups.',
                'Ungroup the specified channel groups.',
                'Remove the specified channel groups.',
                'Display a listing of channel groups.',
                'Store a newly created channel group.',
                'Display the specified channel group.',
                'Update the specified channel group.',
                'Remove the specified channel group.',
            ],
            'Channel' => [
                'Update the specified channels.',
                'Duplicate the specified channels.',
                'Remove the specified channels.',
                'Sync the channels.',
                'Display a listing of channels.',
                'Store a newly created channel.',
                'Display the specified channel.',
                'Update the specified channel.',
                'Remove the specified channel.',
                'Display a listing of channel layers.',
                'Store a newly created channel layer.',
                'Display the specified channel layer.',
                'Update the specified channel layer.',
                'Remove the specified channel.',
                'Sync the channels.',
            ],
            'Playlist Page Group' => [
                'Update the specified page groups.',
                'Duplicate the specified page groups.',
                'Ungroup the specified page groups.',
                'Remove the specified page groups.',
                'Restore the specified page groups.',
                'Display a listing of page groups.',
                'Store a newly created page group.',
                'Display the specified page group.',
                'Update the specified page group.',
                'Remove the specified page group.',
            ],
            'Project Playlist Group' => [
                'Update the specified playlist groups.',
                'Duplicate the specified playlist groups.',
                'Ungroup the specified playlist groups.',
                'Remove the specified playlist groups.',
                'Display a listing of playlist groups.',
                'Store a newly created playlist group.',
                'Display the specified playlist group.',
                'Update the specified playlist group.',
                'Remove the specified playlist group.',
            ],
            'Company' => [
                'Store a newly created company.',
                'Display the specified company.',
                'Update the specified company.',
                'Remove the specified company.',
                'Display a listing of companies.',
            ],
            'TemplateGroup' => [
                'Update the specified template groups.',
                'Duplicate the specified template groups.',
                'Ungroup the specified template groups.',
                'Remove the specified template groups.',
                'Display a listing of template groups.',
                'Store a newly created template group.',
                'Display the specified template group.',
                'Update the specified template group.',
                'Remove the specified template group.',
            ],
            'File' => [
                'Remove the specified workspace.',
                'Display a listing of workspaces.',
                'Store a newly created file for company.',
                'Update a file for company.',
            ],
            'UserGroup' => [
                'Update the specified user groups.',
                'Ungroup the specified user groups.',
                'Remove the specified user groups.',
                'Display a listing of user groups.',
                'Store a newly created user group.',
                'Display the specified user group.',
                'Update the specified user group.',
                'Remove the specified user group.',
            ],
            'Project' => [
                'Duplicate the specified project.',
                'Display a listing of projects.',
                'Store a newly created project.',
                'Display the specified project.',
                'Update the specified project.',
                'Remove the specified project.',
            ],
            'Project Playlist' => [
                'Update the specified playlists.',
                'Duplicate the specified playlists.',
                'Remove the specified playlists.',
                'Display a listing of playlists.',
                'Store a newly created playlist.',
                'Display the specified playlist.',
                'Update the specified playlist.',
                'Remove the specified playlist.',
            ],
            'Authentication' => [
                'Sign up the user.',
                'Log the user in',
                'Recover a password.',
                'Reset a password.',
                'Log the user out (Invalidate the token)',
                'Refresh a token.',
                'Impersonate: take by admin company',
                'Impersonate: Leave',
            ],
        ],
    ],
    'examples' => [
        /*
         * If you would like the package to generate the same example values for parameters on each run,
         * set this to any number (eg. 1234)
         */
        'faker_seed' => null,
        /*
         * With API resources and transformers, Scribe tries to generate example models to use in your API responses.
         * By default, Scribe will try the model's factory, and if that fails, try fetching the first from the database.
         * You can reorder or remove strategies here.
         */
        'models_source' => ['factoryCreate', 'factoryMake', 'databaseFirst'],
    ]
];
