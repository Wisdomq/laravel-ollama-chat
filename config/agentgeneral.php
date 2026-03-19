<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AgentGeneral API URL
    |--------------------------------------------------------------------------
    | The FastAPI server wrapping AgentGeneral runs on the host machine.
    | From inside Docker, host.docker.internal resolves to the host.
    | On Windows/WSL, this is localhost:8765.
    |
    */
    'url' => env('AGENTGENERAL_URL', 'http://host.docker.internal:8765'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    | AgentGeneral may take time for skill generation or planning.
    | Default: 60 seconds.
    |
    */
    'timeout' => env('AGENTGENERAL_TIMEOUT', 60),

    'max_wait'         => env('AGENTGENERAL_MAX_WAIT', 180),
    'poll_interval_ms' => env('AGENTGENERAL_POLL_MS', 2000),
];