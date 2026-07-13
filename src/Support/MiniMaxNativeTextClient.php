<?php

declare(strict_types=1);

namespace Escolar\Ai\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Cliente HTTP para {@link https://platform.minimax.io/docs/api-reference/text-post}.
 */
final class MiniMaxNativeTextClient
{
    /**
     * @param  list<array{role: string, content: string, name?: string}>  $messages
     * @return array{
     *     content: string,
     *     prompt_tokens: int,
     *     completion_tokens: int,
     *     model: string
     * }
     */
    public function chatCompletionV2(
        string $origin,
        #[\SensitiveParameter] string $apiKey,
        string $model,
        array $messages,
        int $timeoutSeconds = 120,
    ): array {
        $url = rtrim($origin, '/').MiniMaxNativeConfig::chatCompletionV2Path();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout($timeoutSeconds)
                ->connectTimeout(15)
                ->post($url, [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => 1.0,
                ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Falha de rede ao chamar MiniMax (text/chatcompletion_v2): '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'MiniMax HTTP %s: %s',
                (string) $response->status(),
                $response->body()
            ));
        }

        /** @var array<string, mixed>|null $data */
        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('Resposta MiniMax inválida (JSON).');
        }

        $baseResp = $data['base_resp'] ?? null;
        if (is_array($baseResp) && (int) ($baseResp['status_code'] ?? 0) !== 0) {
            $msg = (string) ($baseResp['status_msg'] ?? 'Erro MiniMax');
            throw new RuntimeException(
                MiniMaxNativeErrorPresenter::fromBaseResponse($msg, $model)
            );
        }

        $content = (string) data_get($data, 'choices.0.message.content', '');
        $usage = $data['usage'] ?? [];
        $promptTokens = (int) (is_array($usage) ? ($usage['prompt_tokens'] ?? $usage['total_tokens'] ?? 0) : 0);
        $completionTokens = (int) (is_array($usage) ? ($usage['completion_tokens'] ?? 0) : 0);
        $respModel = (string) ($data['model'] ?? $model);

        return [
            'content' => $content,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'model' => $respModel,
        ];
    }
}
