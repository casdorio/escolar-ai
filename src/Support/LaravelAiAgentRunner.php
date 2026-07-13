<?php

declare(strict_types=1);

namespace Escolar\Ai\Support;

use Escolar\Ai\Contracts\AiAgentRunner;
use Escolar\Ai\Contracts\AiAgentRunResult;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\Ai;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

/**
 * Implementação real do runner: invoca o SDK `laravel/ai` v0.6.
 *
 * Estratégia: instanciamos o agente via container, lemos `$agent::make()`
 * (helper do trait `Promptable`), e chamamos `prompt()` com overrides
 * opcionais de provider/model.
 *
 * Mantemos todo o acoplamento ao SDK aqui dentro — qualquer ação de
 * negócio do módulo conhece apenas {@see AiAgentRunner}.
 */
class LaravelAiAgentRunner implements AiAgentRunner
{
    public function run(string $agentClass, string $prompt, array $options = []): AiAgentRunResult
    {
        if (! class_exists($agentClass)) {
            throw new \InvalidArgumentException("Agente inexistente: {$agentClass}");
        }

        if (($options['provider'] ?? null) === 'minimax_native') {
            return $this->runMiniMaxNativeChatCompletionV2($agentClass, $prompt, $options);
        }

        /** @var array{key?: string, url?: string}|null $credentialOverride */
        $credentialOverride = $options['credential_override'] ?? null;
        $providerConfigKey = $this->resolveProviderConfigKey($options['provider'] ?? null);

        if (
            is_array($credentialOverride)
            && $providerConfigKey !== null
            && $this->credentialOverrideAffectsConfig($credentialOverride)
        ) {
            return $this->runWithProviderCredentialOverride(
                $agentClass,
                $prompt,
                $options,
                $providerConfigKey,
                $credentialOverride,
            );
        }

        return $this->runAgentPrompt($agentClass, $prompt, $options);
    }

    /**
     * @param  array{key?: string, url?: string}  $credentialOverride
     */
    private function runWithProviderCredentialOverride(
        string $agentClass,
        string $prompt,
        array $options,
        string $providerConfigKey,
        array $credentialOverride,
    ): AiAgentRunResult {
        $configKey = "ai.providers.{$providerConfigKey}";
        $before = Config::get($configKey);
        if (! is_array($before)) {
            throw new \RuntimeException("Configuração inexistente para o provider de IA [{$providerConfigKey}].");
        }

        $merged = $before;
        if (isset($credentialOverride['key']) && is_string($credentialOverride['key']) && $credentialOverride['key'] !== '') {
            $merged['key'] = $credentialOverride['key'];
        }
        if (isset($credentialOverride['url']) && is_string($credentialOverride['url']) && $credentialOverride['url'] !== '') {
            $merged['url'] = $credentialOverride['url'];
        }

        if (($merged['driver'] ?? null) === 'openai') {
            $merged['url'] = OpenAiProviderConfig::ensureValidBaseUrl($merged['url'] ?? null);
        }

        if (($merged['driver'] ?? null) === 'groq' && isset($merged['url']) && is_string($merged['url'])) {
            $merged['url'] = rtrim($merged['url'], '/');
        }

        Config::set($configKey, $merged);
        Ai::forgetInstance($providerConfigKey);

        try {
            return $this->runAgentPrompt($agentClass, $prompt, $options);
        } finally {
            Config::set($configKey, $before);
            Ai::forgetInstance($providerConfigKey);
        }
    }

    /**
     * @param  array{key?: string, url?: string}  $credentialOverride
     */
    private function credentialOverrideAffectsConfig(array $credentialOverride): bool
    {
        if (isset($credentialOverride['key']) && is_string($credentialOverride['key']) && $credentialOverride['key'] !== '') {
            return true;
        }

        return isset($credentialOverride['url']) && is_string($credentialOverride['url']) && $credentialOverride['url'] !== '';
    }

    private function resolveProviderConfigKey(mixed $provider): ?string
    {
        if ($provider instanceof Lab) {
            return $provider->value;
        }

        if (is_string($provider) && $provider !== '') {
            return Lab::tryFrom($provider)?->value ?? $provider;
        }

        return null;
    }

    private function runAgentPrompt(string $agentClass, string $prompt, array $options): AiAgentRunResult
    {
        $this->ensureGlobalOpenAiBaseUrlIfNeeded($options);

        $agent = $agentClass::make();

        $providerOverride = $this->resolveProvider($options['provider'] ?? null);
        $modelOverride = $options['model'] ?? null;
        $timeout = isset($options['timeout']) ? (int) $options['timeout'] : null;
        $attachments = (array) ($options['attachments'] ?? []);

        $started = hrtime(true);

        $response = $agent->prompt(
            prompt: $prompt,
            attachments: $attachments,
            provider: $providerOverride,
            model: $modelOverride,
            timeout: $timeout,
        );

        $latencyMs = (int) ((hrtime(true) - $started) / 1_000_000);

        $structured = $response instanceof StructuredAgentResponse
            ? $response->structured
            : ['text' => $response->text];

        return new AiAgentRunResult(
            provider: $response->meta->provider ?? 'unknown',
            model: $response->meta->model ?? 'unknown',
            tokensInput: $response->usage->promptTokens,
            tokensOutput: $response->usage->completionTokens,
            latencyMs: $latencyMs,
            structured: $structured,
            rawText: $response->text,
            metadata: [
                'invocation_id' => $response->invocationId ?? null,
            ],
        );
    }

    private function resolveProvider(mixed $value): Lab|array|string|null
    {
        if ($value === null || $value instanceof Lab || is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            return Lab::tryFrom($value) ?? $value;
        }

        return null;
    }

    /**
     * Corrige `ai.providers.openai.url` quando vazio ou sem `/v1` (ex.: OPENAI_URL vazio no .env).
     */
    private function ensureGlobalOpenAiBaseUrlIfNeeded(array $options): void
    {
        $key = $this->resolveProviderConfigKey($options['provider'] ?? null);
        if ($key !== 'openai') {
            return;
        }

        $configKey = 'ai.providers.openai';
        $cfg = Config::get($configKey);
        if (! is_array($cfg) || ($cfg['driver'] ?? null) !== 'openai') {
            return;
        }

        $normalized = OpenAiProviderConfig::ensureValidBaseUrl($cfg['url'] ?? null);
        if (($cfg['url'] ?? null) === $normalized) {
            return;
        }

        $cfg['url'] = $normalized;
        Config::set($configKey, $cfg);
        Ai::forgetInstance('openai');
    }

    /**
     * MiniMax API nativa: POST /v1/text/chatcompletion_v2 (não o modo OpenAI `/v1/chat/completions` nem Responses).
     *
     * @param  array<string, mixed>  $options
     */
    private function runMiniMaxNativeChatCompletionV2(string $agentClass, string $prompt, array $options): AiAgentRunResult
    {
        /** @var array{key?: string, url?: string}|null $credentials */
        $credentials = $options['credential_override'] ?? null;
        $apiKey = is_array($credentials) ? ($credentials['key'] ?? null) : null;
        if (! is_string($apiKey) || $apiKey === '') {
            throw new \InvalidArgumentException(
                'Configure a chave API MiniMax para esta unidade (Integrações → IA) ou no .env.'
            );
        }

        $storedUrl = is_array($credentials) && isset($credentials['url']) && is_string($credentials['url'])
            ? $credentials['url']
            : null;
        $origin = MiniMaxNativeConfig::resolveApiOrigin($storedUrl);

        $model = (string) ($options['model'] ?? '');
        if ($model === '') {
            throw new \InvalidArgumentException('Modelo MiniMax ausente nas opções do agente.');
        }

        $agent = $agentClass::make();
        $instruction = (string) $agent->instructions();
        if ($agent instanceof HasStructuredOutput) {
            $schema = $agent->schema(new JsonSchemaTypeFactory);
            $instruction .= "\n\nDevolva APENAS um único objeto JSON válido (sem markdown, sem texto fora do JSON) "
                ."que obedeça ao seguinte schema:\n"
                .json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        $messages = [
            ['role' => 'system', 'content' => $instruction],
            ['role' => 'user', 'content' => $prompt],
        ];

        $started = hrtime(true);
        $client = new MiniMaxNativeTextClient;
        $timeout = isset($options['timeout']) ? (int) $options['timeout'] : 120;
        $result = $client->chatCompletionV2($origin, $apiKey, $model, $messages, max(30, $timeout));
        $latencyMs = (int) ((hrtime(true) - $started) / 1_000_000);

        $raw = $result['content'];
        $structured = $this->decodeStructuredJsonFromMiniMaxAssistantContent($raw);

        return new AiAgentRunResult(
            provider: 'minimax',
            model: $result['model'],
            tokensInput: $result['prompt_tokens'],
            tokensOutput: $result['completion_tokens'],
            latencyMs: $latencyMs,
            structured: $structured,
            rawText: $raw,
            metadata: [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeStructuredJsonFromMiniMaxAssistantContent(string $raw): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            throw new RuntimeException('MiniMax devolveu conteúdo vazio.');
        }

        if (str_starts_with($trimmed, '```')) {
            $trimmed = (string) preg_replace('/^```(?:json)?\s*/i', '', $trimmed);
            $trimmed = (string) preg_replace('/\s*```$/', '', $trimmed);
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new RuntimeException(
                'MiniMax não devolveu JSON válido para o plano estruturado. Tente reformular a pergunta ou verifique o modelo.'
            );
        }

        return $decoded;
    }
}
