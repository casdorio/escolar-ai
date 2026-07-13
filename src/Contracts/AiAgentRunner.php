<?php

declare(strict_types=1);

namespace Escolar\Ai\Contracts;

/**
 * Abstração mínima sobre o SDK `laravel/ai` para permitir testes e troca
 * futura de provider sem acoplamento direto à API do vendor.
 *
 * Implementações:
 *  - {@see \Escolar\Ai\Support\LaravelAiAgentRunner} (produção; chama o SDK real)
 *  - {@see \Escolar\Ai\Support\FakeAgentRunner} (testes; respostas pré-programadas)
 */
interface AiAgentRunner
{
    /**
     * Executa um agente do SDK e devolve o resultado bruto.
     *
     * @param  class-string  $agentClass  classe do agente (subclasse com Promptable)
     * @param  string  $prompt  prompt do usuário
     * @param  array<string, mixed>  $options  opções (provider override, model override etc.)
     */
    public function run(string $agentClass, string $prompt, array $options = []): AiAgentRunResult;
}
