<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'd3' => [
        'accounts' => env('D3_ACCOUNTS', 'd3@admin.com')
    ],

    'bridge' => [
        'host' => env('PORTA_BRIDGE_HOST', ''),
        'port' => env('PORTA_BRIDGE_PORT', 1500),
    ],

    'terraform' => [
        'enabled' => env('TERRAFORM_ENABLED', false),
        'token' => env('TERRAFORM_TOKEN'),
        'workspace' => env('TERRAFORM_WORKSPACE'),
    ],

    'oh-dear' => [
        'token' => env('OH_DEAR_API_TOKEN'),
        'max_sites' => env('OH_DEAR_NUM_MAX_SITES'),
        // people to notify of automation failure. comma delimited
        'failure_emails' => env('OH_DEAR_FAILURE_EMAILS'),
    ],

    'socketio' => [
        // URL /path to use when pinging the socket server
        'ping_path' => env('SOCKETIO_PING_PATH', '/socket.io/?EIO=4&transport=polling'),

        'on-prem' => [
            // current active connection to use (default to main machine socket server)
            'default' => env('ONPREM_SOCKET_CONNECTION', 'localhost'),
            'connections' => [
                // main machine
                'socket-1' => [
                    'host' => env('ONPREM_SOCKET_HOST_1', 'localhost'),
                    'port' => env('ONPREM_SOCKET_PORT_1', 6001),
                ],
                // backup machine
                'socket-2' => [
                    'host' => env('ONPREM_SOCKET_HOST_2', 'localhost'),
                    'port' => env('ONPREM_SOCKET_PORT_2', 6001),
                ],
            ]
        ],
    ],

    'scheduler' => [
        'enabled' => env('SCHEDULER_ENABLED', false),
    ]

];
