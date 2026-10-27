<?php

return [
    // Explicit registry until Master has a Studio domain model. No device controls this value.
    'studios' => array_values(array_filter(explode(',', env('AGENT_STUDIO_IDS', '')))),
    'master_url' => env('AGENT_MASTER_URL', env('APP_URL')),
    'heartbeat_seconds' => 30,
    'session_timeout_seconds' => (int) env('AGENT_SESSION_TIMEOUT_SECONDS', 90),
    'enrollment_minutes' => 10,
    'replay_minutes' => 5,
    'idempotency_hours' => 24,
    'max_payload_bytes' => 65536,
    'oauth' => [
        'authorization_code_minutes' => (int) env('AGENT_OAUTH_CODE_MINUTES', 2),
        'access_token_minutes' => (int) env('AGENT_OAUTH_ACCESS_MINUTES', 60),
        'refresh_token_days' => (int) env('AGENT_OAUTH_REFRESH_DAYS', 30),
    ],
];
