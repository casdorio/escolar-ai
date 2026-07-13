<?php

declare(strict_types=1);

namespace Escolar\Ai\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Encapsula falhas vindas do provider de IA (timeout, rate limit, 5xx, parsing).
 * Sempre persistido em `ai_calls` antes de propagar.
 */
class AiAgentException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $action,
        public readonly ?string $aiCallPublicId = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
