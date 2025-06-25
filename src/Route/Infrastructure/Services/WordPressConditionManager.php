<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Services;

use Illuminate\Container\Container;
use Pollora\Route\Domain\Contracts\ConditionResolverInterface;
use Pollora\Route\Infrastructure\Services\Contracts\WordPressConditionManagerInterface;

/**
 * Infrastructure service for managing WordPress routing conditions.
 *
 * This service manages WordPress condition aliases and their mappings to actual
 * WordPress conditional functions. It implements both the infrastructure-specific
 * interface and the domain contract to provide condition resolution capabilities
 * across architectural boundaries.
 *
 * The manager loads conditions from multiple sources:
 * - Built-in default WordPress conditions
 * - Custom conditions from Laravel configuration
 * - Plugin-specific conditions (WooCommerce, etc.)
 */
class WordPressConditionManager implements WordPressConditionManagerInterface, ConditionResolverInterface
{
    /**
     * Registered WordPress conditions mapped to their aliases.
     * Format: ['wordpress_function' => 'alias' or ['alias1', 'alias2']]
     *
     * @var array<string, string|array<string>>
     */
    private array $conditions = [];

    /**
     * Indicates whether conditions have been loaded from all sources.
     */
    private bool $loaded = false;

    /**
     * Laravel container instance for optional config access.
     */
    private ?Container $container;

    /**
     * Create a new condition manager instance.
     *
     * @param  Container|null  $container  Optional service container
     */
    public function __construct(?Container $container = null)
    {
        $this->container = $container;
    }

    /**
     * Get all registered conditions as alias => function mappings.
     *
     * @return array<string, string> List of condition aliases mapped to functions
     */
    public function getConditions(): array
    {
        $this->ensureConditionsLoaded();

        return $this->parseConditionConfig($this->conditions);
    }

    /**
     * Resolve a condition alias to its WordPress function.
     *
     * This method first checks if the condition is already a WordPress function name.
     * If not found, it searches through the aliases to find the corresponding function.
     *
     * @param  string  $condition  Condition alias or WordPress function name
     * @return string Resolved WordPress function name
     */
    public function resolveCondition(string $condition): string
    {
        $this->ensureConditionsLoaded();

        // First check if it's already a WordPress function (key in conditions)
        if (array_key_exists($condition, $this->conditions)) {
            return $condition;
        }

        // Search through aliases to find the WordPress function
        foreach ($this->conditions as $wpFunction => $aliases) {
            // Handle single alias (string)
            if (is_string($aliases) && $aliases === $condition) {
                return $wpFunction;
            }
            // Handle multiple aliases (array)
            elseif (is_array($aliases) && in_array($condition, $aliases, true)) {
                return $wpFunction;
            }
        }

        // Return original condition if not found (passthrough)
        return $condition;
    }

    /**
     * Add a new condition alias mapping.
     *
     * @param  string  $alias  Alias used in routing configuration
     * @param  string  $function  WordPress conditional function name
     */
    public function addCondition(string $alias, string $function): void
    {
        $this->ensureConditionsLoaded();
        $this->conditions[$alias] = $function;
    }

    /**
     * Load conditions from multiple sources.
     */
    /**
     * Ensure conditions are loaded from defaults and configuration.
     */
    private function ensureConditionsLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loadDefaultConditions();
        $this->loadConfigConditions();

        $this->loaded = true;
    }

    /**
     * Load default WordPress conditions.
     */
    /**
     * Load the built-in set of WordPress conditions.
     *
     * This method defines core WordPress conditions using the new format where
     * WordPress function names are keys and aliases are values. This eliminates
     * issues with numeric string keys being converted to integers.
     */
    private function loadDefaultConditions(): void
    {
        // Initialize with empty array - will be populated by parseConditionConfig
        $defaultConditions = [
            'is_home' => 'home',
            'is_front_page' => 'front',
            'is_single' => 'single',
            'is_singular' => 'singular',
            'is_page' => 'page',
            'is_category' => 'category',
            'is_tag' => 'tag',
            'is_archive' => 'archive',
            'is_search' => 'search',
            'is_404' => '404',
            'is_admin' => 'admin',
            'is_author' => 'author',
            'is_date' => 'date',
            'is_year' => 'year',
            'is_month' => 'month',
            'is_day' => 'day',
            'is_time' => 'time',
            'is_tax' => ['tax', 'taxonomy'],
            'is_attachment' => 'attachment',
            'is_feed' => 'feed',
            'is_trackback' => 'trackback',
            'is_paged' => 'paged',
            'is_preview' => 'preview',
            'is_robots' => 'robots',
            'is_comments_popup' => 'comments_popup',
        ];

        $this->conditions = $defaultConditions;
    }

    /**
     * Load additional conditions from Laravel configuration.
     *
     * This method loads custom condition mappings from the WordPress configuration
     * using the new inverted format where WordPress function names are keys and
     * aliases are values. This format eliminates issues with numeric keys.
     *
     * The configuration is expected to contain:
     * - 'conditions': WordPress function => alias(es) mappings
     * - 'plugin_conditions': Plugin-specific condition groups
     *
     * Each condition can have either a single alias (string) or multiple aliases (array).
     */
    private function loadConfigConditions(): void
    {
        try {
            if ($this->container && $this->container->bound('config')) {
                $config = $this->container->make('config');

                // Load base conditions from wordpress.conditions
                $configConditions = $config->get('wordpress.conditions', []);
                $this->conditions = array_replace($this->conditions, $configConditions);

                // Load plugin-specific conditions
                $pluginConditions = $config->get('wordpress.plugin_conditions', []);
                foreach ($pluginConditions as $pluginName => $conditions) {
                    if (is_array($conditions)) {
                        $this->conditions = array_replace($this->conditions, $conditions);
                    }
                }
            }
        } catch (\Throwable) {
            // Silent fallback - config may not be available
        }
    }

    /**
     * Parse condition configuration from the new inverted format.
     *
     * This method converts the configuration format where WordPress function
     * names are keys and aliases are values into the internal format where
     * aliases are keys and WordPress functions are values.
     *
     * Supports both single aliases (string values) and multiple aliases (array values).
     *
     * @param  array<string, string|array<string>>  $config  Configuration array
     * @return array<string, string> Parsed conditions with alias => function mapping
     *
     * @example
     * // Input:  ['is_404' => ['404', 'not_found'], 'is_search' => 'search']
     * // Output: ['404' => 'is_404', 'not_found' => 'is_404', 'search' => 'is_search']
     */
    private function parseConditionConfig(array $config): array
    {
        $parsed = [];

        foreach ($config as $wpFunction => $aliases) {
            // Handle single alias (string)
            if (is_string($aliases)) {
                $parsed[$aliases] = $wpFunction;
            }
            // Handle multiple aliases (array)
            elseif (is_array($aliases)) {
                foreach ($aliases as $alias) {
                    if (is_string($alias)) {
                        $parsed[$alias] = $wpFunction;
                    }
                }
            }
        }

        return $parsed;
    }
}
