<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Domain\Contracts;

/**
 * Interface for pattern validator.
 * 
 * Validates pattern data before registration.
 */
interface PatternValidatorInterface
{
    /**
     * Validate a pattern's data and registration status.
     *
     * @param  array<string, mixed>  $patternData  Pattern data to validate
     * @param  string  $file  Path to the pattern file
     * @return bool Whether the pattern is valid and can be registered
     */
    public function isValid(array $patternData, string $file): bool;
} 