<?php

declare(strict_types=1);

namespace Pollora\Gutenberg\Helpers;

/**
 * Helper class for validating block patterns.
 *
 * Ensures patterns meet the required criteria before registration
 * and handles error logging for invalid patterns.
 */
class PatternValidator
{
    /**
     * Validate a pattern's data and registration status.
     *
     * @param  array<string, mixed>  $patternData  Pattern data to validate
     * @param  string  $file  Path to the pattern file
     * @return bool Whether the pattern is valid and can be registered
     */
    public function isValid(array $patternData, string $file): bool
    {
        if (! $this->isValidPattern($patternData)) {
            $this->logPatternError($file, $patternData);

            return false;
        }

        return ! $this->isPatternRegistered($patternData['slug']);
    }

    /**
     * Check if pattern has required fields.
     *
     * @param  array<string, mixed>  $patternData  Pattern data to check
     * @return bool Whether the pattern has all required fields
     */
    private function isValidPattern(array $patternData): bool
    {
        return ! empty($patternData['slug']) && ! empty($patternData['title']);
    }

    /**
     * Check if pattern is already registered.
     *
     * @param  string  $slug  Pattern slug to check
     * @return bool Whether the pattern is already registered
     */
    private function isPatternRegistered(string $slug): bool
    {
        return \WP_Block_Patterns_Registry::get_instance()->is_registered($slug);
    }

    /**
     * Log pattern validation errors.
     *
     * @param  string  $file  Path to the pattern file
     * @param  array<string, mixed>  $patternData  Pattern data that failed validation
     */
    protected function logPatternError(string $file, array $patternData): void
    {
        $message = empty($patternData['slug'])
            ? __('Could not register file "%s" as a block pattern ("Slug" field missing)')
            : __('Could not register file "%s" as a block pattern ("Title" field missing)');

        _doing_it_wrong('_register_theme_block_patterns', sprintf($message, $file), '6.0.0');
    }
}
