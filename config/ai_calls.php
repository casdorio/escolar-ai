<?php

/*
 * Defaults do núcleo de IA (pacote escolar/ai).
 *
 * Cada app pode publicar/definir seu próprio config/ai_calls.php (o do APP é a
 * referência completa, com a seção `agents`) — os valores do app têm
 * precedência sobre estes defaults via mergeConfigFrom.
 */
return [
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
