<?php

$provider = strtolower(trim((string) env('ASSISTANT_PROVIDER', '')));

return [
    'remote_enabled' => filter_var(env('ASSISTANT_REMOTE_ENABLED', false), FILTER_VALIDATE_BOOL),
    'provider' => in_array($provider, ['n8n'], true) ? $provider : null,
    'locale' => 'es-EC',
    'timeout_seconds' => min(max((int) env('ASSISTANT_TIMEOUT_SECONDS', 8), 2), 15),
    'rate_limit_per_minute' => min(max((int) env('ASSISTANT_RATE_LIMIT_PER_MINUTE', 20), 1), 120),
    'max_question_length' => min(max((int) env('ASSISTANT_MAX_QUESTION_LENGTH', 500), 100), 1000),
    'max_answer_length' => 2000,
    'n8n' => [
        'webhook_url' => trim((string) env('ASSISTANT_N8N_WEBHOOK_URL', '')),
        'secret' => (string) env('ASSISTANT_N8N_SECRET', ''),
        'ingest_webhook_url' => trim((string) env('ASSISTANT_N8N_INGEST_WEBHOOK_URL', '')),
        'ingest_secret' => (string) env('ASSISTANT_N8N_INGEST_SECRET', ''),
        'ingest_timeout_seconds' => min(max((int) env('ASSISTANT_N8N_INGEST_TIMEOUT_SECONDS', 30), 5), 60),
    ],
];
