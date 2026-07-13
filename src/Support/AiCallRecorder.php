<?php

declare(strict_types=1);

namespace Escolar\Ai\Support;

use Escolar\Ai\Models\AiCall;
use Carbon\CarbonImmutable;

/**
 * Persiste e consulta `ai_calls` de forma centralizada.
 *
 * O recorder concentra:
 *  - resolução do tenant_id corrente (via stancl/tenancy quando disponível)
 *  - cálculo de custo USD a partir de tokens (placeholder até pricing real)
 *  - somatório do gasto diário usado pelo cost guard
 */
class AiCallRecorder
{
    /**
     * Cria uma row de sucesso.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordSuccess(
        string $action,
        string $provider,
        string $model,
        int $tokensInput,
        int $tokensOutput,
        int $latencyMs,
        ?string $agentClass = null,
        int|string|null $tenantId = null,
        ?int $schoolId = null,
        ?float $costUsd = null,
        array $metadata = [],
    ): AiCall {
        return AiCall::create([
            'tenant_id' => $this->normalizeTenantId($tenantId),
            'school_id' => $schoolId,
            'action' => $action,
            'agent_class' => $agentClass,
            'provider' => $provider,
            'model' => $model,
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
            'cost_usd' => $costUsd ?? $this->estimateCostUsd($provider, $model, $tokensInput, $tokensOutput),
            'latency_ms' => $latencyMs,
            'success' => true,
            'metadata' => $metadata !== [] ? $metadata : null,
        ]);
    }

    /**
     * Cria uma row de falha (chamada não retornou sucesso do provider).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordFailure(
        string $action,
        string $provider,
        string $model,
        string $errorMessage,
        ?string $agentClass = null,
        int|string|null $tenantId = null,
        ?int $schoolId = null,
        int $latencyMs = 0,
        array $metadata = [],
    ): AiCall {
        return AiCall::create([
            'tenant_id' => $this->normalizeTenantId($tenantId),
            'school_id' => $schoolId,
            'action' => $action,
            'agent_class' => $agentClass,
            'provider' => $provider,
            'model' => $model,
            'tokens_input' => 0,
            'tokens_output' => 0,
            'cost_usd' => 0,
            'latency_ms' => $latencyMs,
            'success' => false,
            'error_message' => $errorMessage,
            'metadata' => $metadata !== [] ? $metadata : null,
        ]);
    }

    /**
     * Soma o custo (USD) das chamadas do tenant no dia corrente.
     * Usado pelo cost guard antes de invocar o provider.
     */
    public function dailyCostUsd(int|string|null $tenantId, ?CarbonImmutable $day = null): float
    {
        $day ??= CarbonImmutable::now();

        return (float) AiCall::query()
            ->when(
                $tenantId !== null,
                fn ($q) => $q->where('tenant_id', (string) $tenantId),
                fn ($q) => $q->whereNull('tenant_id'),
            )
            ->whereBetween('created_at', [$day->startOfDay(), $day->endOfDay()])
            ->sum('cost_usd');
    }

    /**
     * Estimativa simples baseada em tabela genérica (US$ por 1k tokens).
     * Substituir por pricing real (provider × model) quando AI-07 for implementado.
     */
    public function estimateCostUsd(string $provider, string $model, int $tokensInput, int $tokensOutput): float
    {
        $pricing = config("ai_calls.pricing.{$provider}.{$model}");

        if (! is_array($pricing)) {
            $pricing = config("ai_calls.pricing.{$provider}._default", [
                'input_per_1k' => 0.003,
                'output_per_1k' => 0.015,
            ]);
        }

        $input = ($tokensInput / 1000) * (float) ($pricing['input_per_1k'] ?? 0);
        $output = ($tokensOutput / 1000) * (float) ($pricing['output_per_1k'] ?? 0);

        return round($input + $output, 6);
    }

    private function normalizeTenantId(int|string|null $explicit): ?string
    {
        if ($explicit !== null) {
            return (string) $explicit;
        }

        return $this->resolveTenantId();
    }

    private function resolveTenantId(): ?string
    {
        if (! function_exists('tenant')) {
            return null;
        }

        $id = tenant('id');

        return $id !== null ? (string) $id : null;
    }
}
