<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ComfyUI Server URL
    |--------------------------------------------------------------------------
    | The base URL of your ComfyUI server on the local network.
    | Example: http://192.168.1.50:8188
    | Set COMFYUI_URL in your .env file.
    */
    'base_url' => env('COMFYUI_URL', 'http://172.16.10.11:8188'),

    /*
    |--------------------------------------------------------------------------
    | Polling
    |--------------------------------------------------------------------------
    | How often (in seconds) the frontend polls for job completion.
    | poll_timeout is how many seconds before we give up polling.
    */
    'poll_interval' => env('COMFYUI_POLL_INTERVAL', 4),
    'poll_timeout'  => env('COMFYUI_POLL_TIMEOUT', 300),

    /*
    |--------------------------------------------------------------------------
    | Workflow Templates Directory
    |--------------------------------------------------------------------------
    | Where your ComfyUI workflow JSON template files are stored.
    | These live in storage/app/workflows/
    */
    'templates_path' => storage_path('app/workflows'),

];