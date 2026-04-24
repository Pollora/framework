<?php

declare(strict_types=1);

namespace Pollora\Dashboard\UI\Console;

use Illuminate\Console\Command;
use Pollora\Dashboard\Domain\Services\SystemInfoCollector;

/**
 * CLI complement to the admin dashboard.
 *
 * Displays a summary of the Pollora framework status including version,
 * environment, discovery stats, and cache state. Supports --json for
 * machine-readable output (useful for AI agents and CI pipelines).
 */
final class StatusCommand extends Command
{
    protected $signature = 'pollora:status {--json : Output as JSON}';

    protected $description = 'Display Pollora framework status and system information';

    public function handle(SystemInfoCollector $collector): int
    {
        $info = $collector->collect();

        if ($this->option('json')) {
            $this->line((string) json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->renderFrameworkStatus($info['framework']);
        $this->renderEnvironment($info['environment']);
        $this->renderWordPress($info['wordpress']);
        $this->renderDiscovery($info['discovery']);
        $this->renderModules($info['modules']);
        $this->renderCache($info['cache']);
        $this->renderPerformance($info['performance']);
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
     * @param  array{debug: bool, multisite: bool, permalink_structure: string}  $wordpress
     */
    private function renderWordPress(array $wordpress): void
    {
        $this->line(sprintf(
            '  WP_DEBUG: %s | Multisite: %s | Permalinks: %s',
            $wordpress['debug'] ? 'on' : 'off',
            $wordpress['multisite'] ? 'yes' : 'no',
            $wordpress['permalink_structure']
        ));
        $this->newLine();
    }

    /**
     * @param  array<string, mixed>  $discovery
     */
    private function renderDiscovery(array $discovery): void
    {
        // Post Types
        $postTypes = $discovery['post_types'];
        $this->line(sprintf('  Post Types: %d registered (via discovery)', $postTypes['count']));
        foreach ($postTypes['items'] as $item) {
            $this->line(sprintf('    · %s [%s] — %s', $item['label'], $item['slug'], $item['class']));
        }

        // Taxonomies
        $taxonomies = $discovery['taxonomies'];
        $this->line(sprintf('  Taxonomies: %d registered', $taxonomies['count']));
        foreach ($taxonomies['items'] as $item) {
            $this->line(sprintf('    · %s [%s] — %s', $item['label'], $item['slug'], $item['class']));
        }

        // Hooks
        $hooks = $discovery['hooks'];
        $this->line(sprintf(
            '  Hooks: %d registered (%d actions, %d filters)',
            $hooks['count'],
            $hooks['actions'],
            $hooks['filters']
        ));

        // REST Routes
        $rest = $discovery['rest_routes'];
        $this->line(sprintf('  REST API routes: %d registered', $rest['count']));
        foreach ($rest['items'] as $item) {
            $this->line(sprintf('    · %s', $item['class']));
        }

        // WP-CLI
        $cli = $discovery['wp_cli_commands'];
        $this->line(sprintf('  WP-CLI commands: %d registered', $cli['count']));
        foreach ($cli['items'] as $item) {
            $this->line(sprintf('    · %s', $item['class']));
        }

        // Schedules
        $schedules = $discovery['schedules'];
        $this->line(sprintf('  Scheduled tasks: %d registered', $schedules['count']));
        foreach ($schedules['items'] as $item) {
            $this->line(sprintf('    · %s::%s()', $item['class'], $item['method']));
        }

        // Service Providers
        $providers = $discovery['service_providers'];
        $this->line(sprintf('  Auto-discovered providers: %d', $providers['count']));
        foreach ($providers['items'] as $item) {
            $this->line(sprintf('    · %s', $item['class']));
        }

        $this->newLine();
    }

    /**
     * @param  array{count: int, enabled: int, disabled: int, items: list<array{name: string, status: string, description: string, priority: string}>}  $modules
     */
    private function renderModules(array $modules): void
    {
        $this->line(sprintf(
            '  Modules: %d total (%d enabled, %d disabled)',
            $modules['count'],
            $modules['enabled'],
            $modules['disabled']
        ));

        foreach ($modules['items'] as $module) {
            $status = $module['status'] === 'enabled' ? '✓' : '✗';
            $this->line(sprintf('    %s %s', $status, $module['name']));
        }

        $this->newLine();
    }

    /**
     * @param  array{driver: string, enabled: bool}  $cache
     */
    private function renderCache(array $cache): void
    {
        $status = $cache['enabled'] ? 'enabled' : 'disabled';

        $this->line(sprintf('  Discovery cache: %s (%s)', $status, $cache['driver']));
    }

    /**
     * @param  array<string, mixed>  $performance
     */
    private function renderPerformance(array $performance): void
    {
        $context = $performance['context'] ?? [];

        $parts = [];
        if (isset($context['cache_hits'])) {
            $parts[] = sprintf('%d cache hits', $context['cache_hits']);
        }

        if (isset($context['cache_misses'])) {
            $parts[] = sprintf('%d misses', $context['cache_misses']);
        }

        if (isset($context['classes_processed'])) {
            $parts[] = sprintf('%d classes', $context['classes_processed']);
        }

        if ($parts !== []) {
            $this->line(sprintf('  Discovery stats: %s', implode(', ', $parts)));
        }

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
