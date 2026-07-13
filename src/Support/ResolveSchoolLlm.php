<?php

declare(strict_types=1);

namespace Escolar\Ai\Support;

use Escolar\Ai\Exceptions\NoLlmConfiguredException;

/**
 * Fase 9 (governança de IA) — gateway único de resolução de LLM por escola.
 *
 * Recebe `schoolId` + `agentKey` (chave em `ai_calls.agents.*`) e devolve
 * provider/model/credenciais já resolvidos, embrulhando o
 * {@see SchoolLlmConfigResolver}. É o ÚNICO ponto que combina "defaults do
 * agente (`ai_calls`)" + "override por escola (`school_llm_settings`)": todos
 * os pontos de IA (Actions, orchestrators, OCR) passam por aqui, em vez de ler
 * `config('ai_calls.agents.X...')` direto e ignorar a LLM default da unidade.
 *
 * Quando não há LLM utilizável (sem cadastro na escola E sem credencial global
 * para o provider do agente), {@see forAgent()} lança
 * {@see NoLlmConfiguredException}. {@see isAvailable()} faz a mesma checagem
 * sem lançar — usado para a flag `ai_available` na UI.
 */
final readonly class ResolveSchoolLlm
{
    public function __construct(
        private SchoolLlmConfigResolver $resolver,
    ) {}

    /**
     * Provider/model/credential_override prontos para o `AiAgentRunner`.
     *
     * @return array{provider: string, model: string, credential_override: ?array{key?: string, url?: string}}
     *
     * @throws NoLlmConfiguredException quando não há LLM utilizável.
     */
    public function forAgent(?int $schoolId, string $agentKey): array
    {
        $resolved = $this->resolve($schoolId, $agentKey);

        if (! $this->hasUsableCredentials($resolved)) {
            throw new NoLlmConfiguredException($schoolId, $agentKey);
        }

        return $resolved;
    }

    /**
     * Há LLM utilizável para esta escola/ação? (não lança — para flags de UI).
     */
    public function isAvailable(?int $schoolId, string $agentKey): bool
    {
        return $this->hasUsableCredentials($this->resolve($schoolId, $agentKey));
    }

    /**
     * Limite diário de custo (USD) — passthrough do resolver, para que os
     * consumidores dependam apenas do gateway (single entry de governança).
     */
    public function dailyCostLimitUsd(?int $tenantId, ?int $schoolId): float
    {
        return $this->resolver->dailyCostLimitUsd($tenantId, $schoolId);
    }

    /**
     * Flag geral de "há IA utilizável" para a UI (item 88): a escola tem uma
     * LLM default+ativa cadastrada, OU o provider default global tem credencial.
     * Não é por-agente (decisão fina fica no {@see forAgent()}); serve para
     * desabilitar/avisar botões de IA quando não há nada configurado.
     */
    public function isAnyAvailable(?int $schoolId): bool
    {
        if ($schoolId !== null && $this->resolver->providerPayload($schoolId) !== null) {
            return true;
        }

        $defaultProvider = (string) config('ai.default', 'openai');
        $key = config("ai.providers.{$defaultProvider}.key");

        return is_string($key) && trim($key) !== '';
    }

    /**
     * @return array{provider: string, model: string, credential_override: ?array{key?: string, url?: string}}
     */
    private function resolve(?int $schoolId, string $agentKey): array
    {
        $defaultProvider = (string) config(
            "ai_calls.agents.{$agentKey}.provider",
            (string) config('ai.default', 'openai')
        );
        $defaultModel = (string) config("ai_calls.agents.{$agentKey}.model", '');

        return $this->resolver->resolveForAgent($schoolId, $defaultProvider, $defaultModel);
    }

    /**
     * Credencial utilizável = override da escola traz `key`, OU o provider
     * (runtime) tem chave no config global (`ai.providers.{provider}.key`).
     * Sem nenhuma das duas, não há como chamar o provider.
     *
     * @param  array{provider: string, model: string, credential_override: ?array{key?: string, url?: string}}  $resolved
     */
    private function hasUsableCredentials(array $resolved): bool
    {
        if (trim($resolved['model']) === '') {
            return false;
        }

        $override = $resolved['credential_override'];
        if (is_array($override) && isset($override['key']) && trim((string) $override['key']) !== '') {
            return true;
        }

        $globalKey = config("ai.providers.{$resolved['provider']}.key");

        return is_string($globalKey) && trim($globalKey) !== '';
    }
}
