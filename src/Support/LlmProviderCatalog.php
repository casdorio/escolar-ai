<?php

declare(strict_types=1);

namespace Escolar\Ai\Support;

/**
 * Catálogo de providers/modelos de LLM oferecidos na tela de configuração
 * por unidade (Fase 4C) — fonte única consumida pelo frontend (seleção
 * guiada) e pela validação do {@see \App\Modules\Admin\Requests\SchoolLlmSettingRequest}.
 *
 * As chaves (`value`) batem com as chaves de `config/ai.php > providers`,
 * que é o que o {@see LaravelAiAgentRunner} usa para resolver o driver — ou
 * seja, só expomos providers que o runner realmente sabe executar.
 *
 * `models` é uma lista *sugerida* (não exaustiva): o usuário sempre pode
 * digitar um modelo customizado. `needs_api_key`/`supports_base_url`
 * apenas guiam a UI.
 */
final class LlmProviderCatalog
{
    /**
     * @return list<array{value: string, label: string, models: list<string>, needs_api_key: bool, supports_base_url: bool, hint: string}>
     */
    public static function all(): array
    {
        return [
            [
                'value' => 'anthropic',
                'label' => 'Anthropic (Claude)',
                'models' => ['claude-opus-4-8', 'claude-sonnet-4-6', 'claude-haiku-4-5'],
                'needs_api_key' => true,
                'supports_base_url' => true,
                'hint' => 'Claude — equilíbrio de qualidade e custo para textos escolares.',
            ],
            [
                'value' => 'openai',
                'label' => 'OpenAI',
                'models' => ['gpt-4o', 'gpt-4o-mini', 'o3', 'o3-mini'],
                'needs_api_key' => true,
                'supports_base_url' => true,
                'hint' => 'GPT-4o / o3 — ampla compatibilidade.',
            ],
            [
                'value' => 'gemini',
                'label' => 'Google (Gemini)',
                'models' => ['gemini-2.0-flash', 'gemini-1.5-pro', 'gemini-1.5-flash'],
                'needs_api_key' => true,
                'supports_base_url' => true,
                'hint' => 'Gemini Flash — rápido e econômico.',
            ],
            [
                'value' => 'groq',
                'label' => 'Groq',
                'models' => ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant'],
                'needs_api_key' => true,
                'supports_base_url' => true,
                'hint' => 'Inferência rápida de modelos abertos.',
            ],
            [
                'value' => 'mistral',
                'label' => 'Mistral',
                'models' => ['mistral-large-latest', 'mistral-small-latest'],
                'needs_api_key' => true,
                'supports_base_url' => true,
                'hint' => 'Modelos europeus, bom custo-benefício.',
            ],
            [
                'value' => 'deepseek',
                'label' => 'DeepSeek',
                'models' => ['deepseek-chat', 'deepseek-reasoner'],
                'needs_api_key' => true,
                'supports_base_url' => true,
                'hint' => 'Custo baixo; bom para tarefas de raciocínio.',
            ],
            [
                'value' => 'openrouter',
                'label' => 'OpenRouter',
                'models' => [],
                'needs_api_key' => true,
                'supports_base_url' => true,
                'hint' => 'Roteia para vários provedores com uma única chave (informe o model id).',
            ],
            [
                'value' => 'ollama',
                'label' => 'Ollama (local)',
                'models' => ['llama3.1', 'qwen2.5', 'mistral'],
                'needs_api_key' => false,
                'supports_base_url' => true,
                'hint' => 'Modelos locais/self-hosted — informe a Base URL do servidor Ollama.',
            ],
            [
                'value' => 'custom',
                'label' => 'Outro / Custom',
                'models' => [],
                'needs_api_key' => false,
                'supports_base_url' => true,
                'hint' => 'Proxy/endpoint compatível com OpenAI — informe Base URL e model id.',
            ],
        ];
    }

    /**
     * Valores de provider aceitos (para `Rule::in`).
     *
     * @return list<string>
     */
    public static function providerValues(): array
    {
        return array_map(static fn (array $p): string => $p['value'], self::all());
    }
}
