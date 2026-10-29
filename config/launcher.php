<?php

return [
    'studios' => array_values(array_filter(explode(',', env('LAUNCHER_STUDIO_IDS', env('AGENT_STUDIO_IDS', ''))))),
    'heartbeat_seconds' => (int) env('LAUNCHER_HEARTBEAT_SECONDS', 30),
    'max_payload_bytes' => (int) env('LAUNCHER_MAX_PAYLOAD_BYTES', 65536),
];
