<?php

declare(strict_types=1);

namespace Escolar\Ai\Privacy;

/**
 * Pseudonimização de PII antes de enviar texto a um LLM externo (LGPD by
 * design — D1 do PLANO-MESTRE).
 *
 * Estratégia (defesa-em-profundidade, prefere super-mascarar a vazar):
 *  1. PII estruturada por regex — pega automaticamente em QUALQUER agente:
 *     e-mail, CNPJ/CPF/CEP formatados, telefone formatado e sequências de
 *     8–14 dígitos (docs/telefones sem formatação).
 *  2. Termos conhecidos (nomes de alunos/responsáveis) que o chamador passa em
 *     `sensitive_terms` — casados por palavra inteira, case-insensitive.
 *
 * Tokens estáveis `[[TIPO_N]]`: o mesmo valor recebe sempre o mesmo token, e a
 * reidratação ({@see rehydrate}) reverte na resposta.
 */
class PiiAnonymizer
{
    /**
     * Ordem importa: padrões formatados (mais específicos) antes do catch-all
     * de dígitos. Nomes entram por último (classe de caractere diferente).
     *
     * @var array<string, string> regex => tipo do token
     */
    private const PATTERNS = [
        '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/' => 'EMAIL',
        '/\d{2}\.\d{3}\.\d{3}\/\d{4}\-\d{2}/' => 'CNPJ',
        '/\d{3}\.\d{3}\.\d{3}\-\d{2}/' => 'CPF',
        '/\d{5}\-\d{3}/' => 'CEP',
        '/\(?\d{2}\)?\s?9?\d{4}\-\d{4}/' => 'TEL',
        '/\b\d{8,14}\b/' => 'NUM',
    ];

    /**
     * @param  list<string>  $terms  valores conhecidos a mascarar (ex.: nomes)
     */
    public function anonymize(string $text, array $terms = []): AnonymizationResult
    {
        $map = [];        // token => original
        $counters = [];   // tipo => n

        $tokenFor = function (string $value, string $type) use (&$map, &$counters): string {
            foreach ($map as $tok => $orig) {
                if ($orig === $value) {
                    return $tok;
                }
            }
            $n = $counters[$type] = ($counters[$type] ?? 0) + 1;
            $tok = "[[{$type}_{$n}]]";
            $map[$tok] = $value;

            return $tok;
        };

        foreach (self::PATTERNS as $pattern => $type) {
            $text = (string) preg_replace_callback(
                $pattern,
                fn (array $m): string => $tokenFor($m[0], $type),
                $text,
            );
        }

        foreach ($terms as $term) {
            $term = trim((string) $term);
            if (mb_strlen($term) < 3) {
                continue; // termos curtos dariam falso-positivo demais
            }
            $quoted = preg_quote($term, '/');
            $text = (string) preg_replace_callback(
                '/\b'.$quoted.'\b/iu',
                function (array $m) use (&$map, &$counters): string {
                    foreach ($map as $tok => $orig) {
                        if (mb_strtolower($orig) === mb_strtolower($m[0])) {
                            return $tok;
                        }
                    }
                    $n = $counters['NOME'] = ($counters['NOME'] ?? 0) + 1;
                    $tok = "[[NOME_{$n}]]";
                    $map[$tok] = $m[0];

                    return $tok;
                },
                $text,
            );
        }

        return new AnonymizationResult($text, $map);
    }

    /**
     * Reverte os tokens para os valores originais.
     *
     * @param  array<string, string>  $map  token → original
     */
    public function rehydrate(string $text, array $map): string
    {
        if ($map === []) {
            return $text;
        }

        // strtr faz longest-match, então [[NUM_11]] é revertido antes de [[NUM_1]].
        return strtr($text, $map);
    }
}
