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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'didit' => [
        'base_url' => env('DIDIT_BASE_URL', 'https://verification.didit.me'),
        'api_key' => env('DIDIT_API_KEY'),
        'workflow_id' => env('DIDIT_WORKFLOW_ID'),
        'webhook_secret' => env('DIDIT_WEBHOOK_SECRET'),
        'webhook_url' => env('DIDIT_WEBHOOK_URL'),
        'evidence_disk' => env('DIDIT_EVIDENCE_DISK', 'local'),
    ],

    'velvet' => [
        'whatsapp_number' => env('VELVET_WHATSAPP_NUMBER'),
    ],

    'ably' => [
        'api_key' => env('ABLY_API_KEY'),
        'channel' => env('ABLY_CHANNEL', 'admin-leads'),
        'notification_channel' => env('ABLY_NOTIFICATION_CHANNEL', env('ABLY_CHANNEL', 'admin-leads')),
        'channels' => [
            'leads' => env('ABLY_LEADS_CHANNEL', 'admin-leads'),
            'interviews' => env('ABLY_INTERVIEWS_CHANNEL', 'admin-interviews'),
            'onboarding' => env('ABLY_ONBOARDING_CHANNEL', 'admin-onboarding'),
            'rooms' => env('ABLY_ROOMS_CHANNEL', 'admin-rooms'),
            'station_manager' => env('ABLY_STATION_MANAGER_CHANNEL', 'admin-station-manager'),
        ],
    ],

];
