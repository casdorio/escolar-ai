<?php

declare(strict_types=1);

namespace Escolar\Ai\Support;

/**
 * Garante URL base compatível com Prism + driver OpenAI do laravel/ai.
 *
 * O cliente HTTP monta caminhos relativos (`responses`, `chat/completions`, …)
 * sobre a base. Se `OPENAI_URL` estiver vazio ou for `https://api.openai.com`
 * sem `/v1`, o POST vira `…/responses` no host errado e a API devolve 404.
 */
final class OpenAiProviderConfig
{
    public const DEFAULT_BASE_URL = 'https://api.openai.com/v1';

    public static function ensureValidBaseUrl(?string $url): string
    {
        $url = is_string($url) ? trim($url) : '';
        if ($url === '') {
            return self::DEFAULT_BASE_URL;
        }

        $url = rtrim($url, '/');
        if (str_ends_with($url, '/v1')) {
            return $url;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return self::DEFAULT_BASE_URL;
        }

        $host = $parts['host'] ?? '';
        $path = $parts['path'] ?? '';

        if ($host === 'api.openai.com' && ($path === '' || $path === '/')) {
            $scheme = $parts['scheme'] ?? 'https';

            return "{$scheme}://api.openai.com/v1";
        }

        return $url;
    }
}
