<?php

declare(strict_types=1);

namespace Escolar\Ai\Exceptions;

use DomainException;

/**
 * Lançada antes de chamar o provider quando o tenant ultrapassou o limite
 * diário configurado (config `ai_calls.cost_guard.default_daily_limit_usd`
 * ou override por tenant).
 */
class AiCostLimitExceeded extends DomainException
{
    public function __construct(
        public readonly ?int $tenantId,
        public readonly float $usedUsd,
        public readonly float $limitUsd,
        public readonly string $action,
    ) {
        parent::__construct(sprintf(
            'Limite diário de custo de IA excedido para tenant %s na ação "%s": usado US$ %.4f, limite US$ %.4f.',
            $tenantId !== null ? (string) $tenantId : '(global)',
            $action,
            $usedUsd,
            $limitUsd,
        ));
    }
}
