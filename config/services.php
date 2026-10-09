<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'jg_agente' => [
        'url' => env('JG_AGENTE_URL', 'http://127.0.0.1:8001'),
        'secret' => env('JG_AGENTE_SECRET', 'filmmaker_dev_secret_key_12345'),
        'mock' => env('JG_AGENTE_MOCK', false),
        'timeout' => env('JG_AGENTE_TIMEOUT', 30),
    ],

    'llm' => [
        'provider' => env('LLM_PROVIDER', 'groq'),
        'model' => env('LLM_MODEL', 'llama-3.3-70b-versatile'),
        'api_key' => env('LLM_API_KEY', env('GROQ_API_KEY', env('GEMINI_API_KEY'))),
        'timeout' => env('LLM_TIMEOUT', 45),
    ],

];
