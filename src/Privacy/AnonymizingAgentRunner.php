<?php

declare(strict_types=1);

namespace Escolar\Ai\Privacy;

use Escolar\Ai\Contracts\AiAgentRunner;
use Escolar\Ai\Contracts\AiAgentRunResult;
use Illuminate\Support\Facades\Log;

/**
 * Decorator de {@see AiAgentRunner} que pseudonimiza o prompt antes de chamar o
 * LLM e reidrata a resposta (D1 — LGPD by design). Envolve o runner real
 * (`LaravelAiAgentRunner`) via bind no {@see \App\Providers\AiModuleServiceProvider}.
 *
 * Opções relevantes em `$options`:
 *  - `sensitive_terms`: list<string> — valores a mascarar além da PII estruturada
 *    (nomes de alunos/responsáveis). O chamador que monta o prompt os fornece.
 *  - `skip_anonymization`: bool — exceção controlada (ex.: visão de documento, que
 *    PRECISA do texto cru). Registra a exceção em log (trilha LGPD).
 *  - `anonymization_bypass_reason`: string — motivo do bypass (auditoria).
 */
class AnonymizingAgentRunner implements AiAgentRunner
{
    public function __construct(
        private readonly AiAgentRunner $inner,
        private readonly PiiAnonymizer $anonymizer,
    ) {}

    public function run(string $agentClass, string $prompt, array $options = []): AiAgentRunResult
    {
        if (($options['skip_anonymization'] ?? false) === true) {
            Log::warning('[ai.anonymization] bypass', [
                'agent' => $agentClass,
                'reason' => $options['anonymization_bypass_reason'] ?? 'unspecified',
            ]);

            return $this->inner->run($agentClass, $prompt, $options);
        }

        $terms = is_array($options['sensitive_terms'] ?? null)
            ? array_values(array_filter($options['sensitive_terms'], 'is_string'))
            : [];

        $anon = $this->anonymizer->anonymize($prompt, $terms);

        $result = $this->inner->run($agentClass, $anon->text, $options);

        if (! $anon->hasReplacements()) {
            return $result;
        }

        return new AiAgentRunResult(
            provider: $result->provider,
            model: $result->model,
            tokensInput: $result->tokensInput,
            tokensOutput: $result->tokensOutput,
            latencyMs: $result->latencyMs,
            structured: $this->rehydrateArray($result->structured, $anon->map),
            rawText: $this->anonymizer->rehydrate($result->rawText, $anon->map),
            metadata: $result->metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $map
     * @return array<string, mixed>
     */
    private function rehydrateArray(array $data, array $map): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = $this->anonymizer->rehydrate($value, $map);
            } elseif (is_array($value)) {
                $data[$key] = $this->rehydrateArray($value, $map);
            }
        }

        return $data;
    }
}
