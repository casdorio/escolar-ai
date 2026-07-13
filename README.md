# escolar/ai — núcleo compartilhado de IA

Fundação de IA do GESTAO ESCOLAR, extraída do `APP/app/Modules/Ai` (F-AI-1) e
consumida por **APP**, **RESPONSALUNO** e **PROFESSOR-portal** via composer
path repository (`"escolar/ai": "@dev"`).

## O que vive aqui (namespace `Escolar\Ai`)

| Área | Conteúdo |
|---|---|
| `Contracts` | `AiAgentRunner`, `AiAgentRunResult` |
| `Privacy` | `PiiAnonymizer`, `AnonymizingAgentRunner`, `AnonymizationResult` — anonimização LGPD (D1) |
| `Support` | `LaravelAiAgentRunner` (runner real), `FakeAgentRunner` (testes), `AiCallRecorder` (auditoria + cost guard), `ResolveSchoolLlm` + `SchoolLlmConfigResolver` (LLM por escola), catálogo de providers, ponte MiniMax/proxies |
| `Exceptions` | `AiAgentException`, `AiCostLimitExceeded`, `NoLlmConfiguredException` |
| `Models` | `AiCall`, `SchoolLlmSetting` (canônicos; os apps podem estender) |
| `AiCoreServiceProvider` | auto-descoberto: binda `AiAgentRunner` → runner real decorado pela anonimização; recorder singleton; normaliza URL OpenAI; merge de defaults `ai_calls` |

## O que fica em cada app

- **Agentes, Actions, DTOs, Prompts, Tools, Jobs** — negócio do app.
- OCR por visão (`OcrProvider` + `LaravelAiVisionOcrProvider`) ficou no APP
  (depende de agentes/DTOs do APP).
- `config/ai_calls.php` completo (seção `agents`) — o APP é a referência.

## Regras cross-app

- **laravel/ai é `suggest`**: sem o SDK (ex.: RESPONSALUNO hoje), o núcleo
  funciona (anonimizador, recorder, resolvers, FakeAgentRunner); resolver o
  runner real lança RuntimeException instrutiva. Instale o SDK quando o app
  ganhar chamadas reais (E9).
- **`school_llm_settings.api_key` é `encrypted` com o APP_KEY do app que
  gravou.** Se outro app não decriptar, o resolver degrada para a credencial
  global do provider (warning no log) — nunca quebra. Para chave por escola
  cross-app, os apps precisam compartilhar APP_KEY.
- Mudança no núcleo = mudar AQUI (nunca copiar para os apps). Os 3 apps têm
  smoke tests (`AiCoreSmokeTest`) e o APP roda a suíte completa sobre o pacote.
