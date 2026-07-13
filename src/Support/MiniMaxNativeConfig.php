<?php

declare(strict_types=1);

namespace Escolar\Ai\Support;

/**
 * API nativa MiniMax — Text Generation V2.
 *
 * @see https://platform.minimax.io/docs/api-reference/text-post
 *
 * Servidor OpenAPI: `https://api.minimax.io` + path `/v1/text/chatcompletion_v2`.
 * (Diferente do modo OpenAI-compatible `…/v1/chat/completions`.)
 */
final class MiniMaxNativeConfig
{
    public const DEFAULT_ORIGIN = 'https://api.minimax.io';

    /**
     * Extrai origem `scheme://host` a partir da URL salva (ex.: `https://api.minimax.io/v1`).
     */
    public static function resolveApiOrigin(?string $storedUrl): string
    {
        if ($storedUrl === null || trim($storedUrl) === '') {
            return self::DEFAULT_ORIGIN;
        }

        $parts = parse_url(rtrim(trim($storedUrl), '/'));
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return self::DEFAULT_ORIGIN;
        }

        return $parts['scheme'].'://'.$parts['host'];
    }

    public static function chatCompletionV2Path(): string
    {
        return '/v1/text/chatcompletion_v2';
    }
}
