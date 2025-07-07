<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Domain\Models;

/**
 * Domain model representing raw data extracted from a pattern file.
 *
 * This is a pure domain object with no framework dependencies.
 */
class PatternFileData
{
    /**
     * Create a new pattern file data object.
     *
     * @param  string  $file  Path to the pattern file
     * @param  array<string, string|null>  $headers  Extracted headers from the file
     */
    public function __construct(
        private readonly string $file,
        private array $headers
    ) {}

    /**
     * Get the file path.
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Get all headers.
     *
     * @return array<string, string|null>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific header value.
     *
     * @param  string  $key  Header key
     * @param  string|null  $default  Default value if key not found
     * @return string|null Header value
     */
    public function getHeader(string $key, ?string $default = null): ?string
    {
        return $this->headers[$key] ?? $default;
    }

    /**
     * Check if the pattern data is valid (has required fields).
     */
    public function isValid(): bool
    {
        return ! empty($this->headers['slug']) && (isset($this->headers['title']) && ($this->headers['title'] !== '' && $this->headers['title'] !== '0'));
    }
}
