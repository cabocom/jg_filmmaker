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
        'secret' => env('JG_AGENTE_SECRET', env('PERMANENT_AGENT_SECRET')),
        'key_id' => env('JG_AGENTE_KEY_ID', 'jg_filmmaker_v1'),
        'mock' => (bool) env('JG_AGENTE_MOCK', false),
        'timeout' => (int) env('JG_AGENTE_TIMEOUT', 30),
        'verify_tls' => (bool) env('JG_AGENTE_VERIFY_TLS', true),
    ],

    'llm' => [
        'direct_enabled' => (bool) env('LLM_DIRECT_ENABLED', false),
        'provider' => env('LLM_PROVIDER', 'groq'),
        'model' => env('LLM_MODEL', 'openai/gpt-oss-120b'),
        'api_key' => match (strtolower((string) env('LLM_PROVIDER', 'groq'))) {
            'groq' => env('GROQ_API_KEY', env('LLM_API_KEY')),
            'openai' => env('OPENAI_API_KEY', env('LLM_API_KEY')),
            'gemini', 'google' => env('GEMINI_API_KEY', env('GLOBAL_GOOGLE_API_KEY', env('LLM_API_KEY'))),
            default => env('LLM_API_KEY'),
        },
        'timeout' => (int) env('LLM_TIMEOUT', 45),
    ],

    'filmmaker' => [
        'mock_ffmpeg' => (bool) env('MOCK_FFMPEG', false),
    ],

    'speaches' => [
        'url' => env('SPEACHES_URL', 'http://speaches:8000'),
        'model' => env('SPEACHES_MODEL', 'speaches-ai/piper-es_ES-sharvard-medium'),
        'default_voice' => env('SPEACHES_DEFAULT_VOICE', 'sharvard#M'),
        'timeout' => (int) env('SPEACHES_TIMEOUT', 30),
    ],

    'google_ai' => [
        'api_key' => env('GOOGLE_AI_API_KEY', env('GEMINI_API_KEY', env('GLOBAL_GOOGLE_API_KEY'))),
        'model' => env('GOOGLE_AI_IMAGEN_MODEL', 'imagen-3.0-generate-002'),
        'base_url' => env('GOOGLE_AI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => (int) env('GOOGLE_AI_TIMEOUT', 60),
    ],

];
