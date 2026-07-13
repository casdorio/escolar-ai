<?php

declare(strict_types=1);

namespace Escolar\Ai\Exceptions;

use DomainException;

/**
 * Fase 9 (governança de IA) — lançada quando NÃO há LLM utilizável para uma
 * ação: a escola não tem `SchoolLlmSetting` default+ativa com provider/model
 * E também não há fallback global válido (provider do agente sem credencial).
 *
 * As Actions/orchestrators capturam e devolvem a mensagem amigável; a UI
 * desabilita os botões de IA via flag `ai_available` (ver
 * {@see \Escolar\Ai\Support\ResolveSchoolLlm}).
 */
class NoLlmConfiguredException extends DomainException
{
    public const USER_MESSAGE = 'Nenhuma LLM está configurada e ativa para esta unidade. Configure em Configurações → IA.';

    public function __construct(
        public readonly ?int $schoolId,
        public readonly string $agentKey,
    ) {
        parent::__construct(self::USER_MESSAGE);
    }
}
