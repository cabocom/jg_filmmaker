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
        'model' => (function () {
            $m = env('LLM_MODEL');
            if (empty($m) || $m === 'llama-3.3-70b-versatile') {
                return 'openai/gpt-oss-120b';
            }

            return $m;
        })(),
        'api_key' => (function () {
            $provider = strtolower((string) env('LLM_PROVIDER', 'groq'));
            if ($provider === 'groq') {
                $groqKey = env('GROQ_API_KEY') ?: (env('GLOBAL_GROQ_API_KEY') ?: env('LLM_API_KEY'));
                if (! empty($groqKey) && str_starts_with($groqKey, 'gsk_')) {
                    return $groqKey;
                }
            }
            $key = env('LLM_API_KEY') ?: (env('GROQ_API_KEY') ?: (env('GLOBAL_GROQ_API_KEY') ?: (env('GLOBAL_GOOGLE_API_KEY') ?: env('GEMINI_API_KEY'))));
            if (! empty($key)) {
                return $key;
            }
            $envPath = base_path('.env');
            if (file_exists($envPath)) {
                $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    foreach (['LLM_API_KEY', 'GROQ_API_KEY', 'GLOBAL_GROQ_API_KEY', 'GLOBAL_GOOGLE_API_KEY', 'GEMINI_API_KEY'] as $envKey) {
                        if (str_starts_with($line, $envKey.'=')) {
                            $val = trim(substr($line, strlen($envKey) + 1));
                            $val = trim($val, "\"'");
                            if (! empty($val)) {
                                return $val;
                            }
                        }
                    }
                }
            }

            return null;
        })(),
        'timeout' => env('LLM_TIMEOUT', 45),
    ],

];
