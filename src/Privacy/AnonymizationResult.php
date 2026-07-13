<?php

declare(strict_types=1);

namespace Escolar\Ai\Privacy;

/**
 * Texto pseudonimizado + mapa token→valor original para reidratar depois.
 */
final readonly class AnonymizationResult
{
    /**
     * @param  array<string, string>  $map  token → valor original
     */
    public function __construct(
        public string $text,
        public array $map,
    ) {}

    public function hasReplacements(): bool
    {
        return $this->map !== [];
    }
}
