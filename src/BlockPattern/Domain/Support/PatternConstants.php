<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Domain\Support;

/**
 * Constants used across the BlockPattern module.
 *
 * This class centralizes all constants related to block pattern processing
 * to avoid duplication and ensure consistency across the module.
 *
 * @since 1.0.0
 */
final class PatternConstants
{
    /**
     * Directory path for pattern files relative to theme root.
     */
    public const string PATTERN_DIRECTORY = '/resources/views/patterns/';

    /**
     * File extension for Blade pattern files.
     */
    public const string PATTERN_FILE_EXTENSION = '.blade.php';

    /**
     * File extension for PHP pattern files.
     */
    public const string PHP_FILE_EXTENSION = '.php';

    /**
     * Default viewport width for patterns when none is specified.
     */
    public const int DEFAULT_VIEWPORT_WIDTH = 1200;

    /**
     * Maximum allowed viewport width for patterns.
     */
    public const int MAX_VIEWPORT_WIDTH = 2000;

    /**
     * Default pattern category when none is specified.
     */
    public const string DEFAULT_CATEGORY = 'general';

    /**
     * Pattern file headers configuration.
     *
     * Maps internal property names to WordPress file header names.
     *
     * @var array<string, string>
     */
    public const array DEFAULT_HEADERS = [
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
     * Array properties that should be split by comma.
     *
     * @var array<string>
     */
    public const array ARRAY_PROPERTIES = [
        'categories',
        'keywords',
        'blockTypes',
        'postTypes',
    ];

    /**
     * Boolean properties that need special parsing.
     *
     * @var array<string>
     */
    public const array BOOLEAN_PROPERTIES = [
        'inserter',
    ];

    /**
     * Integer properties that need type casting.
     *
     * @var array<string>
     */
    public const array INTEGER_PROPERTIES = [
        'viewportWidth',
    ];

    /**
     * Translatable properties that support i18n.
     *
     * @var array<string>
     */
    public const array TRANSLATABLE_PROPERTIES = [
        'title',
        'description',
    ];

    /**
     * Valid boolean values for pattern properties.
     *
     * @var array<string>
     */
    public const array TRUE_VALUES = [
        'yes',
        'true',
        '1',
    ];
}
