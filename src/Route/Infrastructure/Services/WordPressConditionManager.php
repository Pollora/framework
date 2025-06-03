<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Services;

use Illuminate\Container\Container;
use Pollora\Route\Infrastructure\Services\Contracts\WordPressConditionManagerInterface;

/**
 * Dedicated class for managing WordPress conditions.
 */
class WordPressConditionManager implements WordPressConditionManagerInterface
{
    private array $conditions = [];
    private bool $loaded = false;
    private ?Container $container;

    public function __construct(?Container $container = null)
    {
        $this->container = $container;
    }

    public function getConditions(): array
    {
        $this->ensureConditionsLoaded();
        return $this->conditions;
    }

    public function resolveCondition(string $condition): string
    {
        $this->ensureConditionsLoaded();
        return $this->conditions[$condition] ?? $condition;
    }

    public function addCondition(string $alias, string $function): void
    {
        $this->ensureConditionsLoaded();
        $this->conditions[$alias] = $function;
    }

    /**
     * Load conditions from multiple sources.
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
    private function loadDefaultConditions(): void
    {
        $this->conditions = [
            'home' => 'is_home',
            'front' => 'is_front_page',
            'single' => 'is_single',
            'singular' => 'is_singular',
            'page' => 'is_page',
            'category' => 'is_category',
            'tag' => 'is_tag',
            'archive' => 'is_archive',
            'search' => 'is_search',
            '404' => 'is_404',
            'admin' => 'is_admin',
            'author' => 'is_author',
            'date' => 'is_date',
            'year' => 'is_year',
            'month' => 'is_month',
            'day' => 'is_day',
            'time' => 'is_time',
            'tax' => 'is_tax',
            'taxonomy' => 'is_tax',
            'attachment' => 'is_attachment',
            'feed' => 'is_feed',
            'trackback' => 'is_trackback',
            'paged' => 'is_paged',
            'preview' => 'is_preview',
            'robots' => 'is_robots',
            'comments_popup' => 'is_comments_popup',
        ];
    }

    /**
     * Load conditions from Laravel config.
     */
    private function loadConfigConditions(): void
    {
        try {
            if ($this->container && $this->container->bound('config')) {
                $config = $this->container->make('config');
                $configConditions = $config->get('wordpress.routing.conditions', []);
                
                $this->conditions = array_merge($this->conditions, $configConditions);
            }
        } catch (\Throwable) {
            // Silent fallback - config may not be available
        }
    }
}