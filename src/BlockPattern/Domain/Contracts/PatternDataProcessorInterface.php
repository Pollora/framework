<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Domain\Contracts;

/**
 * Interface for pattern data processor.
 * 
 * Handles extraction and processing of pattern metadata from files.
 */
interface PatternDataProcessorInterface
{
    /**
     * Extract pattern data from a file.
     *
     * @param  string  $file  Path to the pattern file
     * @return array<string, string|null> Extracted pattern data
     */
    public function getPatternData(string $file): array;
    
    /**
     * Process pattern data for registration.
     *
     * Converts data types, handles internationalization, and filters empty values.
     *
     * @param  array<string, mixed>  $patternData  Raw pattern data
     * @param  object  $theme  Current theme instance
     * @return array<string, mixed> Processed pattern data
     */
    public function process(array $patternData, object $theme): array;
} 