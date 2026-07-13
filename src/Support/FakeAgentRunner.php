<?php

declare(strict_types=1);

namespace Escolar\Ai\Support;

use Escolar\Ai\Contracts\AiAgentRunner;
use Escolar\Ai\Contracts\AiAgentRunResult;
use Closure;
use RuntimeException;

/**
 * Runner para testes: respostas pré-programadas, sem rede.
 *
 * Uso típico:
 *  ```
 *  $runner = (new FakeAgentRunner)->program(PreEnrollmentAgent::class, [...]);
 *  $this->app->instance(AiAgentRunner::class, $runner);
 *  ```
 */
class FakeAgentRunner implements AiAgentRunner
{
    /** @var array<class-string, AiAgentRunResult|Closure(string, array<string, mixed>): AiAgentRunResult> */
    private array $programmed = [];

    /** @var list<array{agent: class-string, prompt: string, options: array<string, mixed>}> */
    public array $calls = [];

    /**
     * Programa o resultado para um agente específico.
     *
     * @param  class-string  $agentClass
     * @param  AiAgentRunResult|array<string, mixed>|Closure(string, array<string, mixed>): AiAgentRunResult  $result
     */
    public function program(string $agentClass, AiAgentRunResult|array|Closure $result): self
    {
        if (is_array($result)) {
            $result = new AiAgentRunResult(
                provider: $result['provider'] ?? 'fake',
                model: $result['model'] ?? 'fake-model',
                tokensInput: $result['tokens_input'] ?? 100,
                tokensOutput: $result['tokens_output'] ?? 50,
                latencyMs: $result['latency_ms'] ?? 5,
                structured: $result['structured'] ?? [],
                rawText: $result['raw_text'] ?? '',
                metadata: $result['metadata'] ?? [],
            );
        }

        $this->programmed[$agentClass] = $result;

        return $this;
    }

    public function run(string $agentClass, string $prompt, array $options = []): AiAgentRunResult
    {
        $this->calls[] = [
            'agent' => $agentClass,
            'prompt' => $prompt,
            'options' => $options,
        ];

        if (! isset($this->programmed[$agentClass])) {
            throw new RuntimeException(
                "FakeAgentRunner sem resposta programada para [{$agentClass}]. Chame ->program(...) antes."
            );
        }

        $value = $this->programmed[$agentClass];

        return $value instanceof Closure ? $value($prompt, $options) : $value;
    }
}
