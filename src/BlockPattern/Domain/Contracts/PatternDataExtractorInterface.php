<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Domain\Contracts;

use Pollora\BlockPattern\Domain\Models\PatternFileData;

/**
 * Port interface for extracting pattern data from files.
 *
 * This is a port in hexagonal architecture that defines how
 * the domain gets pattern metadata from external file systems.
 */
interface PatternDataExtractorInterface
{
    /**
     * Extract pattern data from a file.
     *
     * @param  string  $file  Path to the pattern file
     * @return PatternFileData Extracted data from the file
     */
    public function extractFromFile(string $file): PatternFileData;

    /**
     * Process and transform raw pattern data.
     *
     * @param  PatternFileData  $fileData  Raw pattern file data
     * @param  object  $theme  Theme object for context
     * @return array<string, mixed> Processed pattern data
     */
    public function processData(PatternFileData $fileData, object $theme): array;

    /**
     * Get the rendered content for a pattern.
     *
     * @param  string  $file  Path to the pattern file
     * @return string|null Rendered content or null if not available
     */
    public function getContent(string $file): ?string;
}
