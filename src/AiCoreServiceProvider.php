<?php

declare(strict_types=1);

namespace Escolar\Ai;

use Escolar\Ai\Contracts\AiAgentRunner;
use Escolar\Ai\Privacy\AnonymizingAgentRunner;
use Escolar\Ai\Privacy\PiiAnonymizer;
use Escolar\Ai\Support\AiCallRecorder;
use Escolar\Ai\Support\LaravelAiAgentRunner;
use Escolar\Ai\Support\OpenAiProviderConfig;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Núcleo compartilhado de IA (pacote `escolar/ai`).
 *
 * - Funde defaults do config `ai_calls` (cost guard, pricing) — o
 *   `config/ai_calls.php` de cada app tem precedência.
 * - Bind padrão de {@see AiAgentRunner} → {@see AnonymizingAgentRunner}
 *   decorando o {@see LaravelAiAgentRunner} (D1: toda chamada passa pela
 *   anonimização LGPD). Em testes, sobreponha com
 *   `$this->app->instance(AiAgentRunner::class, new FakeAgentRunner)`.
 * - Se o SDK `laravel/ai` NÃO estiver instalado no app (ex.: RESPONSALUNO),
 *   resolver o runner real lança uma RuntimeException instrutiva — o núcleo
 *   (anonimizador, recorder, resolvers, FakeAgentRunner) funciona sem o SDK.
 */
class AiCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai_calls.php', 'ai_calls');

        $this->app->singleton(AiCallRecorder::class);

        $this->app->bind(AiAgentRunner::class, function ($app): AiAgentRunner {
            if (! class_exists(\Laravel\Ai\Ai::class)) {
                throw new RuntimeException(
                    'O runner real de IA exige o pacote laravel/ai (composer require laravel/ai). '
                    .'Em testes, injete Escolar\\Ai\\Support\\FakeAgentRunner via $app->instance().',
                );
            }

            return new AnonymizingAgentRunner(
                $app->make(LaravelAiAgentRunner::class),
                $app->make(PiiAnonymizer::class),
            );
        });
    }

    public function boot(): void
    {
        $this->normalizeConfiguredOpenAiBaseUrl();
    }

    /**
     * OPENAI_URL vazio no .env vira string vazia (e não o default do config),
     * o que quebra o runtime — normaliza para a URL oficial.
     */
    private function normalizeConfiguredOpenAiBaseUrl(): void
    {
        $cfg = config('ai.providers.openai');
        if (! is_array($cfg)) {
            return;
        }

        $normalized = OpenAiProviderConfig::ensureValidBaseUrl($cfg['url'] ?? null);
        if (($cfg['url'] ?? null) === $normalized) {
            return;
        }

        Config::set('ai.providers.openai.url', $normalized);
    }
}
