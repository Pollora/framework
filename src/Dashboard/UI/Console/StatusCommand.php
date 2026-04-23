<?php

declare(strict_types=1);

namespace Pollora\Dashboard\UI\Console;

use Illuminate\Console\Command;
use Pollora\Dashboard\Domain\Services\SystemInfoCollector;

/**
 * CLI complement to the admin dashboard.
 *
 * Displays a summary of the Pollora framework status including version,
 * environment, discovery stats, and cache state.
 */
final class StatusCommand extends Command
{
    protected $signature = 'pollora:status';

    protected $description = 'Display Pollora framework status and system information';

    public function handle(SystemInfoCollector $collector): int
    {
        $info = $collector->collect();

        $this->renderFrameworkStatus($info['framework']);
        $this->renderEnvironment($info['environment']);
        $this->renderDiscovery($info['discovery']);
        $this->renderCache($info['cache']);
        $this->renderTheme($info['theme']);

        return self::SUCCESS;
    }

    /**
     * @param  array{current: ?string, latest: ?string, update_available: bool}  $framework
     */
    private function renderFrameworkStatus(array $framework): void
    {
        $current = $framework['current'] ?? 'unknown';
        $latest = $framework['latest'] ?? 'unknown';
        $updateAvailable = $framework['update_available'];
        $isDev = is_string($current) && str_starts_with($current, 'dev-');

        if ($isDev) {
            $this->line(sprintf('Pollora %s (latest stable: v%s)', $current, $latest));
        } elseif ($updateAvailable) {
            $this->warn(sprintf('Pollora v%s (latest: v%s) ↑', $current, $latest));
        } else {
            $this->info(sprintf('Pollora v%s (latest: v%s) ✓', $current, $latest));
        }

        $this->newLine();
    }

    /**
     * @param  array{php: string, laravel: string, wordpress: string}  $environment
     */
    private function renderEnvironment(array $environment): void
    {
        $this->line(sprintf(
            '  PHP %s | Laravel %s | WordPress %s',
            $environment['php'],
            $environment['laravel'],
            $environment['wordpress']
        ));
        $this->newLine();
    }

    /**
     * @param  array{post_types: array{count: int, items: list<array{class: string, slug: string, label: string}>}, taxonomies: array{count: int, items: list<array{class: string, slug: string, label: string}>}, hooks: array{count: int, actions: int, filters: int}}  $discovery
     */
    private function renderDiscovery(array $discovery): void
    {
        $this->line(sprintf(
            '  Post Types: %d registered (via discovery)',
            $discovery['post_types']['count']
        ));

        foreach ($discovery['post_types']['items'] as $item) {
            $this->line(sprintf('    · %s [%s] — %s', $item['label'], $item['slug'], $item['class']));
        }

        $this->line(sprintf(
            '  Taxonomies: %d registered',
            $discovery['taxonomies']['count']
        ));

        foreach ($discovery['taxonomies']['items'] as $item) {
            $this->line(sprintf('    · %s [%s] — %s', $item['label'], $item['slug'], $item['class']));
        }

        $this->line(sprintf(
            '  Hooks: %d registered (%d actions, %d filters)',
            $discovery['hooks']['count'],
            $discovery['hooks']['actions'],
            $discovery['hooks']['filters']
        ));

        $this->newLine();
    }

    /**
     * @param  array{driver: string, enabled: bool}  $cache
     */
    private function renderCache(array $cache): void
    {
        $status = $cache['enabled'] ? 'enabled' : 'disabled';

        $this->line(sprintf(
            '  Discovery cache: %s (%s)',
            $status,
            $cache['driver']
        ));

        $this->newLine();
    }

    /**
     * @param  array{name: string, version: string, template: string}  $theme
     */
    private function renderTheme(array $theme): void
    {
        $this->line(sprintf(
            '  Theme: %s v%s (%s)',
            $theme['name'],
            $theme['version'],
            $theme['template']
        ));
    }
}
