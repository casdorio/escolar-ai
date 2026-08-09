<?php

declare(strict_types=1);

namespace Escolar\Ai\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Cast do `api_key` de `school_llm_settings` — criptografa/decripta com uma
 * chave DEDICADA (`config('ai_calls.credentials_key')`, de `LLM_CREDENTIALS_KEY`
 * no `.env`), IGUAL em todos os apps que leem essa tabela compartilhada
 * (ADMIN grava, APP/PROFESSOR-portal/RESPONSALUNO leem).
 *
 * Antes, o cast era `'encrypted'` puro (`APP_KEY` de cada app) — como cada
 * app normalmente tem sua própria `APP_KEY`, nenhum app além do que gravou a
 * linha conseguia decriptar (ver {@see \Escolar\Ai\Support\SchoolLlmConfigResolver::safeApiKey()}).
 *
 * Sem `LLM_CREDENTIALS_KEY` configurada (ex.: ambiente que ainda não migrou),
 * cai no `Crypt` padrão do app — mesmo comportamento de antes, para não
 * quebrar nada durante a transição.
 *
 * Falha de decriptação (chave errada/ausente, linha gravada no esquema
 * antigo) lança {@see \Illuminate\Contracts\Encryption\DecryptException},
 * do mesmo jeito que o cast `'encrypted'` nativo — o resolver já trata isso.
 */
class EncryptedCredentialCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return $this->encrypter()->decrypt($value, false);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return null;
        }

        return $this->encrypter()->encrypt($value, false);
    }

    private function encrypter(): \Illuminate\Contracts\Encryption\Encrypter
    {
        $key = config('ai_calls.credentials_key');

        if (empty($key)) {
            return Crypt::getFacadeRoot();
        }

        $parsedKey = Str::startsWith($key, 'base64:')
            ? base64_decode(Str::after($key, 'base64:'))
            : $key;

        return new Encrypter($parsedKey, config('app.cipher', 'AES-256-CBC'));
    }
}
