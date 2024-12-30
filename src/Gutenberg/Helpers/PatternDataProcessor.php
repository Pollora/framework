<?php

declare(strict_types=1);

namespace Pollora\Gutenberg\Helpers;

/**
 * Helper class for processing block pattern data.
 * 
 * Handles the extraction and processing of pattern metadata from files,
 * including internationalization and data type conversion.
 */
class PatternDataProcessor
{
    /**
     * Default headers to extract from pattern files.
     *
     * @var array<string, string>
     */
    protected array $defaultHeaders = [
        'title' => 'Title',
        'slug' => 'Slug',
        'description' => 'Description',
        'viewportWidth' => 'Viewport Width',
        'categories' => 'Categories',
        'keywords' => 'Keywords',
        'blockTypes' => 'Block Types',
        'postTypes' => 'Post Types',
        'inserter' => 'Inserter',
    ];

    /**
     * Extract pattern data from a file.
     *
     * @param string $file Path to the pattern file
     * @return array<string, string|null> Extracted pattern data
     */
    public function getPatternData(string $file): array
    {
        return get_file_data($file, $this->defaultHeaders);
    }

    /**
     * Process pattern data for registration.
     * 
     * Converts data types, handles internationalization, and filters empty values.
     *
     * @param array<string, mixed> $patternData Raw pattern data
     * @param \WP_Theme $theme Current theme instance
     * @return array<string, mixed> Processed pattern data
     */
    public function process(array $patternData, \WP_Theme $theme): array
    {
        $arrayProperties = ['categories', 'keywords', 'blockTypes', 'postTypes'];

        return collect($patternData)
            ->map(function ($value, $key) use ($arrayProperties, $theme) {
                if (in_array($key, $arrayProperties)) {
                    return $value ? explode(',', $value) : null;
                }
                if ($key === 'viewportWidth') {
                    return $value ? (int) $value : null;
                }
                if ($key === 'inserter') {
                    return $value ? in_array(strtolower($value), ['yes', 'true']) : null;
                }
                if (in_array($key, ['title', 'description'])) {
                    $context = $key === 'title' ? 'Pattern title' : 'Pattern description';

                    return translate_with_gettext_context($value, $context, $theme->get('TextDomain'));
                }

                return $value;
            })
            ->filter()
            ->all();
    }
}
