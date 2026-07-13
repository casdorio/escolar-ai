<?php

declare(strict_types=1);

namespace Escolar\Ai\Support;

use Escolar\Ai\Models\SchoolLlmSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve limites e overrides de LLM por escola sobre o config global (`ai_calls.php`).
 *
 * Único lugar para provider/model/chave/URL e limite diário USD, consumido
 * pelas actions de IA de todos os apps via {@see ResolveSchoolLlm}.
 *
 * Cross-app: `api_key` é `encrypted` com o APP_KEY do app que gravou a linha.
 * Se este app não conseguir decriptar, a chave é tratada como ausente (warning
 * no log) e o runner cai nas credenciais globais do provider — nunca quebra.
 */
final class SchoolLlmConfigResolver
{
    public function dailyCostLimitUsd(?int $tenantId, ?int $schoolId): float
    {
        if ($schoolId !== null && Schema::hasTable('school_llm_settings')) {
            $row = SchoolLlmSetting::query()
                ->where('school_id', $schoolId)
                ->where('is_default', true)
                ->first();
            if ($row !== null && $row->is_enabled && $row->daily_cost_limit_usd !== null) {
                return (float) $row->daily_cost_limit_usd;
            }
        }

        $tenantOverride = $tenantId !== null
            ? config("ai_calls.tenants.{$tenantId}.daily_limit_usd")
            : null;

        return (float) (
            $tenantOverride
            ?? config('ai_calls.cost_guard.default_daily_limit_usd', 5.0)
        );
    }

    /**
     * @return array{provider: string, model: string, api_key: ?string, base_url: ?string}|null
     *                                                                                          null = usar apenas defaults do agente / config global
     */
    public function providerPayload(?int $schoolId): ?array
    {
        if ($schoolId === null || ! Schema::hasTable('school_llm_settings')) {
            return null;
        }

        $row = SchoolLlmSetting::query()
            ->where('school_id', $schoolId)
            ->where('is_default', true)
            ->first();
        if ($row === null || ! $row->is_enabled) {
            return null;
        }

        return $this->payloadFromRow($row);
    }

    /**
     * Extrai provider/model/chave/URL de uma linha (salva ou transitória).
     * `null` = linha incompleta (sem provider/model) → usar defaults do agente.
     *
     * @return array{provider: string, model: string, api_key: ?string, base_url: ?string}|null
     */
    public function payloadFromRow(SchoolLlmSetting $row): ?array
    {
        $provider = $row->provider ? trim($row->provider) : '';
        $model = $row->model ? trim($row->model) : '';
        if ($provider === '' || $model === '') {
            return null;
        }

        $baseUrl = null;
        if ($row->use_custom_base_url && $row->base_url) {
            $baseUrl = trim($row->base_url);
            $baseUrl = $baseUrl !== '' ? $baseUrl : null;
        }

        return [
            'provider' => $provider,
            'model' => $model,
            'api_key' => $this->safeApiKey($row),
            'base_url' => $baseUrl,
        ];
    }

    /**
     * `api_key` é `encrypted` (APP_KEY do app que gravou). Em outro app, a
     * decriptação pode falhar — degrada para null (credencial global do
     * provider) em vez de quebrar a chamada de IA.
     */
    private function safeApiKey(SchoolLlmSetting $row): ?string
    {
        try {
            return $row->api_key;
        } catch (DecryptException $e) {
            Log::warning('escolar/ai: api_key de school_llm_settings não decriptável neste app (APP_KEY difere?) — usando credencial global.', [
                'school_id' => $row->school_id,
                'setting_id' => $row->id,
            ]);

            return null;
        }
    }

    /**
     * Opções de runner (provider/model/credential_override) a partir de uma
     * linha — usado pelo botão "Testar" da tela de configuração com valores
     * ainda não salvos. Reusa o mesmo mapeamento de credenciais e a ponte
     * MiniMax/proxy do {@see resolveForAgent}.
     *
     * @return array{provider: string, model: string, credential_override: ?array{key?: string, url?: string}}|null
     */
    public function runnerOptionsFromRow(SchoolLlmSetting $row): ?array
    {
        $payload = $this->payloadFromRow($row);

        return $payload === null ? null : $this->buildRunnerOptions($payload);
    }

    /**
     * Une defaults do agente (`ai_calls`) com overrides por escola para o runner de IA.
     *
     * @return array{
     *     provider: string,
     *     model: string,
     *     credential_override: ?array{key?: string, url?: string}
     * }
     */
    public function resolveForAgent(?int $schoolId, string $defaultProvider, string $defaultModel): array
    {
        $payload = $this->providerPayload($schoolId);
        if ($payload === null) {
            return $this->resolveAgentDefaultsWithoutSchool($defaultProvider, $defaultModel);
        }

        return $this->buildRunnerOptions($payload);
    }

    /**
     * Converte um payload (provider/model/chave/URL) nas opções do runner,
     * aplicando o override de credenciais e a ponte para runtimes não-oficiais
     * (MiniMax-native / proxies OpenAI-compatíveis).
     *
     * @param  array{provider: string, model: string, api_key: ?string, base_url: ?string}  $payload
     * @return array{provider: string, model: string, credential_override: ?array{key?: string, url?: string}}
     */
    private function buildRunnerOptions(array $payload): array
    {
        $override = [];
        $apiKey = $payload['api_key'];
        if (is_string($apiKey) && $apiKey !== '') {
            $override['key'] = $apiKey;
        }
        $baseUrl = $payload['base_url'];
        if (is_string($baseUrl) && $baseUrl !== '') {
            $override['url'] = $payload['provider'] === 'openai'
                ? OpenAiProviderConfig::ensureValidBaseUrl($baseUrl)
                : $baseUrl;
        }

        $declaredProvider = $payload['provider'];
        $urlForBridge = $override['url'] ?? null;
        $runtimeProvider = $this->mapOpenAiToNonOfficialRuntime($declaredProvider, $urlForBridge);

        return [
            'provider' => $runtimeProvider,
            'model' => $payload['model'],
            'credential_override' => $override === [] ? null : $override,
        ];
    }

    /**
     * Quando não há linha por escola: ainda assim permite OPENAI_URL global apontar para MiniMax etc.
     *
     * @return array{
     *     provider: string,
     *     model: string,
     *     credential_override: ?array{key?: string, url?: string}
     * }
     */
    private function resolveAgentDefaultsWithoutSchool(string $defaultProvider, string $defaultModel): array
    {
        if ($defaultProvider !== 'openai') {
            return [
                'provider' => $defaultProvider,
                'model' => $defaultModel,
                'credential_override' => null,
            ];
        }

        $globalUrl = config('ai.providers.openai.url');
        if (! is_string($globalUrl) || trim($globalUrl) === '') {
            return [
                'provider' => 'openai',
                'model' => $defaultModel,
                'credential_override' => null,
            ];
        }

        $normalized = OpenAiProviderConfig::ensureValidBaseUrl($globalUrl);
        if (! $this->shouldBridgeNonOfficialOpenAi('openai', $normalized)) {
            return [
                'provider' => 'openai',
                'model' => $defaultModel,
                'credential_override' => null,
            ];
        }

        $override = ['url' => $normalized];
        $globalKey = config('ai.providers.openai.key');
        if (is_string($globalKey) && $globalKey !== '') {
            $override['key'] = $globalKey;
        }

        return [
            'provider' => $this->mapOpenAiToNonOfficialRuntime('openai', $normalized),
            'model' => $defaultModel,
            'credential_override' => $override,
        ];
    }

    /**
     * - **api.minimax.io**: API nativa `POST /v1/text/chatcompletion_v2` (ver doc text-post).
     * - Outros hosts (proxies OpenAI-compatible): driver `groq` → `POST …/v1/chat/completions`.
     *
     * O driver `openai` do laravel/ai usa a API **Responses** (`…/v1/responses`), inexistente nesses casos.
     */
    private function mapOpenAiToNonOfficialRuntime(string $declaredProvider, ?string $baseUrl): string
    {
        if (! $this->shouldBridgeNonOfficialOpenAi($declaredProvider, $baseUrl)) {
            return $declaredProvider;
        }

        $normalized = OpenAiProviderConfig::ensureValidBaseUrl($baseUrl ?? '');
        $host = strtolower((string) parse_url($normalized, PHP_URL_HOST));

        if (str_ends_with($host, 'minimax.io')) {
            return 'minimax_native';
        }

        return 'groq';
    }

    private function shouldBridgeNonOfficialOpenAi(string $provider, ?string $baseUrl): bool
    {
        if ($provider !== 'openai') {
            return false;
        }

        if ($baseUrl === null || trim($baseUrl) === '') {
            return false;
        }

        $normalized = OpenAiProviderConfig::ensureValidBaseUrl($baseUrl);
        $host = strtolower((string) parse_url($normalized, PHP_URL_HOST));

        if ($host === '' || $host === 'api.openai.com') {
            return false;
        }

        if (str_ends_with($host, '.openai.azure.com') || str_ends_with($host, '.azure.com')) {
            return false;
        }

        return true;
    }
}
