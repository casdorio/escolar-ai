<?php

declare(strict_types=1);

namespace Escolar\Ai\Contracts;

/**
 * Resultado normalizado de uma chamada ao SDK `laravel/ai`, suficiente
 * para alimentar `AiCallRecorder` sem expor objetos do vendor.
 */
final readonly class AiAgentRunResult
{
    /**
     * @param  array<string, mixed>  $structured  payload do structured output (ou ['text' => ...] para texto livre)
     * @param  array<string, mixed>  $metadata  qualquer metadado relevante (invocation_id, finish_reason etc.)
     */
    public function __construct(
        public string $provider,
        public string $model,
        public int $tokensInput,
        public int $tokensOutput,
        public int $latencyMs,
        public array $structured,
        public string $rawText = '',
        public array $metadata = [],
    ) {}
}
