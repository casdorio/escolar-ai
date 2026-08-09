<?php

/*
 * Defaults do núcleo de IA (pacote escolar/ai).
 *
 * Cada app pode publicar/definir seu próprio config/ai_calls.php (o do APP é a
 * referência completa, com a seção `agents`) — os valores do app têm
 * precedência sobre estes defaults via mergeConfigFrom.
 */
return [
    // Chave dedicada pra (de/)criptar `school_llm_settings.api_key`
    // ({@see \Escolar\Ai\Casts\EncryptedCredentialCast}) — IGUAL em todos os
    // apps que leem essa tabela (ADMIN grava, APP/PROFESSOR-portal/
    // RESPONSALUNO leem). Gere com `php artisan key:generate --show` (mesmo
    // formato de APP_KEY) e copie o MESMO valor pro `.env` de cada app.
    'credentials_key' => env('LLM_CREDENTIALS_KEY'),

    'cost_guard' => [
        'enabled' => (bool) env('AI_COST_GUARD_ENABLED', true),
        'default_daily_limit_usd' => (float) env('AI_COST_GUARD_DAILY_LIMIT_USD', 5.0),
    ],

    'tenants' => [
        // <tenant_id> => ['daily_limit_usd' => 50.0],
    ],

    'agents' => [
        // Definidos por app (ver APP/config/ai_calls.php como referência).
    ],

    'pricing' => [
        'anthropic' => [
            '_default' => ['input_per_1k' => 0.003, 'output_per_1k' => 0.015],
        ],
        'openai' => [
            '_default' => ['input_per_1k' => 0.0025, 'output_per_1k' => 0.01],
        ],
    ],
];
