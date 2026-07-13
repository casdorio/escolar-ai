<?php

declare(strict_types=1);

namespace Escolar\Ai\Support;

/**
 * Traduz mensagens comuns da API MiniMax (`base_resp`) para texto útil em PT-BR.
 */
final class MiniMaxNativeErrorPresenter
{
    public static function fromBaseResponse(string $statusMessage, string $requestedModel): string
    {
        $raw = trim($statusMessage);
        $lower = strtolower($raw);

        if ($raw === '') {
            return 'A API MiniMax recusou a requisição (sem detalhe no retorno).';
        }

        if (
            str_contains($lower, 'token plan not support')
            || str_contains($lower, 'plan not support')
            || str_contains($lower, 'not support model')
        ) {
            return sprintf(
                'O plano da sua conta MiniMax não inclui o modelo «%s». No painel MiniMax (conta / faturamento), confira quais modelos o seu plano permite; em seguida altere o campo Modelo em Configurações → IA desta unidade (por exemplo MiniMax-M2, MiniMax-M2.1 ou uma variante listada para o seu plano). Detalhe original: %s',
                $requestedModel,
                $raw
            );
        }

        if (
            str_contains($lower, 'authentication')
            || str_contains($lower, 'invalid api key')
            || str_contains($lower, 'unauthorized')
        ) {
            return sprintf(
                'Falha de autenticação na MiniMax. Verifique a chave de API nas configurações. (API: %s)',
                $raw
            );
        }

        if (str_contains($lower, 'insufficient balance') || str_contains($lower, 'balance')) {
            return sprintf(
                'Saldo ou créditos insuficientes na conta MiniMax. (API: %s)',
                $raw
            );
        }

        return 'MiniMax: '.$raw;
    }
}
