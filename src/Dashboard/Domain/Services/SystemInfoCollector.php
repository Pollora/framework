<?php

declare(strict_types=1);

namespace Pollora\Dashboard\Domain\Services;

use Illuminate\Support\Str;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\VersionCheck\Domain\Services\VersionComparator;
use Psr\Container\ContainerInterface;
use Spatie\StructureDiscoverer\Cache\NullDiscoverCacheDriver;

/**
 * Collects system information about the Pollora framework and its environment.
 *
 * Gathers version data, discovery statistics, cache state, modules,
 * WordPress config, and environment details for the admin dashboard
 * and CLI status command.
 */
final readonly class SystemInfoCollector
{
    public function __construct(
        private VersionComparator $versionComparator,
        private DiscoveryManager $discoveryManager,
        private ContainerInterface $container,
        private string $laravelVersion = '',
    ) {}

    /**
     * Collect all system information.
     *
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        return [
            'framework' => $this->collectFrameworkInfo(),
            'environment' => $this->collectEnvironmentInfo(),
            'wordpress' => $this->collectWordPressConfig(),
            'discovery' => $this->collectDiscoveryInfo(),
            'performance' => $this->collectPerformanceInfo(),
            'cache' => $this->collectCacheInfo(),
            'modules' => $this->collectModulesInfo(),
            'theme' => $this->collectThemeInfo(),
        ];
    }

    /**
     * @return array{current: ?string, latest: ?string, update_available: bool}
     */
    public function collectFrameworkInfo(): array
    {
        return [
            'current' => $this->versionComparator->getCurrentVersion(),
            'latest' => $this->versionComparator->getLatestVersion(),
            'update_available' => $this->versionComparator->isUpdateAvailable(),
        ];
    }

    /**
     * @return array{php: string, laravel: string, wordpress: string}
     */
    public function collectEnvironmentInfo(): array
    {
        $wpVersion = function_exists('get_bloginfo')
            ? (string) get_bloginfo('version')
            : 'Unknown';

        return [
            'php' => PHP_VERSION,
            'laravel' => $this->laravelVersion,
            'wordpress' => $wpVersion,
        ];
    }

    /**
     * @return array{debug: bool, multisite: bool, permalink_structure: string}
     */
    public function collectWordPressConfig(): array
    {
        $debug = defined('WP_DEBUG') && WP_DEBUG;
        $multisite = function_exists('is_multisite') && is_multisite();
        $permalink = function_exists('get_option')
            ? (string) get_option('permalink_structure', '')
            : '';

        return [
            'debug' => $debug,
            'multisite' => $multisite,
            'permalink_structure' => $permalink !== '' ? $permalink : 'Plain',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function collectDiscoveryInfo(): array
    {
        return [
            'post_types' => $this->collectPostTypeInfo(),
            'taxonomies' => $this->collectTaxonomyInfo(),
            'hooks' => $this->collectHookInfo(),
            'rest_routes' => $this->collectRestRouteInfo(),
            'wp_cli_commands' => $this->collectWpCliInfo(),
            'schedules' => $this->collectScheduleInfo(),
            'service_providers' => $this->collectServiceProviderInfo(),
        ];
    }

    /**
     * @return array{count: int, items: list<array{class: string, slug: string, label: string}>}
     */
    private function collectPostTypeInfo(): array
    {
        try {
            $items = $this->discoveryManager->getDiscoveredItems('post_types');
            $result = [];

            foreach ($items as $item) {
                if (! isset($item['class'])) {
                    continue;
                }

                $class = $item['class'];
                $slug = Str::kebab(class_basename($class));
                $label = $this->getPostTypeLabel($slug, $class);

                $result[] = [
                    'class' => $class,
                    'slug' => $slug,
                    'label' => $label,
                ];
            }

            return ['count' => count($result), 'items' => $result];
        } catch (\Throwable) {
            return ['count' => 0, 'items' => []];
        }
    }

    /**
     * @return array{count: int, items: list<array{class: string, slug: string, label: string}>}
     */
    private function collectTaxonomyInfo(): array
    {
        try {
            $items = $this->discoveryManager->getDiscoveredItems('taxonomies');
            $result = [];

            foreach ($items as $item) {
                if (! isset($item['class'])) {
                    continue;
                }

                $class = $item['class'];
                $slug = Str::kebab(class_basename($class));
                $label = $this->getTaxonomyLabel($slug, $class);

                $result[] = [
                    'class' => $class,
                    'slug' => $slug,
                    'label' => $label,
                ];
            }

            return ['count' => count($result), 'items' => $result];
        } catch (\Throwable) {
            return ['count' => 0, 'items' => []];
        }
    }

    private function getPostTypeLabel(string $slug, string $class): string
    {
        if (function_exists('get_post_type_object')) {
            $object = get_post_type_object($slug);

            if ($object !== null && isset($object->labels->name)) {
                return $object->labels->name;
            }
        }

        return Str::headline(class_basename($class));
    }

    private function getTaxonomyLabel(string $slug, string $class): string
    {
        if (function_exists('get_taxonomy')) {
            $object = get_taxonomy($slug);

            if ($object !== false && isset($object->labels->name)) {
                return $object->labels->name;
            }
        }

        return Str::headline(class_basename($class));
    }

    /**
     * @return array{count: int, actions: int, filters: int}
     */
    private function collectHookInfo(): array
    {
        try {
            $items = $this->discoveryManager->getDiscoveredItems('hooks');
            $actions = 0;
            $filters = 0;

            foreach ($items as $item) {
                match ($item['type'] ?? null) {
                    'action' => $actions++,
                    'filter' => $filters++,
                    default => null,
                };
            }

            return ['count' => $actions + $filters, 'actions' => $actions, 'filters' => $filters];
        } catch (\Throwable) {
            return ['count' => 0, 'actions' => 0, 'filters' => 0];
        }
    }

    /**
     * @return array{count: int, items: list<array{class: string}>}
     */
    private function collectRestRouteInfo(): array
    {
        return $this->collectDiscoveryItemsByClass('wp_rest_routes');
    }

    /**
     * @return array{count: int, items: list<array{class: string}>}
     */
    private function collectWpCliInfo(): array
    {
        return $this->collectDiscoveryItemsByClass('wp_cli_commands');
    }

    /**
     * @return array{count: int, items: list<array{class: string, method: string}>}
     */
    private function collectScheduleInfo(): array
    {
        try {
            $items = $this->discoveryManager->getDiscoveredItems('schedules');
            $result = [];

            foreach ($items as $item) {
                if (! isset($item['class'], $item['method'])) {
                    continue;
                }

                $result[] = [
                    'class' => $item['class'],
                    'method' => $item['method'],
                ];
            }

            return ['count' => count($result), 'items' => $result];
        } catch (\Throwable) {
            return ['count' => 0, 'items' => []];
        }
    }

    /**
     * @return array{count: int, items: list<array{class: string}>}
     */
    private function collectServiceProviderInfo(): array
    {
        return $this->collectDiscoveryItemsByClass('service_providers');
    }

    /**
     * @return array{count: int, items: list<array{class: string}>}
     */
    private function collectDiscoveryItemsByClass(string $identifier): array
    {
        try {
            $items = $this->discoveryManager->getDiscoveredItems($identifier);
            $result = [];

            foreach ($items as $item) {
                if (isset($item['class'])) {
                    $result[] = ['class' => $item['class']];
                }
            }

            return ['count' => count($result), 'items' => $result];
        } catch (\Throwable) {
            return ['count' => 0, 'items' => []];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function collectPerformanceInfo(): array
    {
        return $this->discoveryManager->getPerformanceStats();
    }

    /**
     * @return array{driver: string, enabled: bool}
     */
    public function collectCacheInfo(): array
    {
        try {
            $engine = $this->discoveryManager->getEngine();
            /** @phpstan-ignore method.notFound */
            $cacheDriver = $engine->getCacheDriver();
            $driverName = $cacheDriver !== null ? (new \ReflectionClass($cacheDriver))->getShortName() : 'None';

            return [
                'driver' => $driverName,
                'enabled' => $cacheDriver !== null && ! ($cacheDriver instanceof NullDiscoverCacheDriver),
            ];
        } catch (\Throwable) {
            return ['driver' => 'Unknown', 'enabled' => false];
        }
    }

    /**
     * @return array{count: int, enabled: int, disabled: int, items: list<array{name: string, status: string, description: string, priority: string}>}
     */
    public function collectModulesInfo(): array
    {
        try {
            /** @var RepositoryInterface $modules */
            $modules = $this->container->get('modules');
            $all = $modules->all();
            $enabled = $modules->allEnabled();
            $disabled = $modules->allDisabled();

            $items = [];
            foreach ($all as $module) {
                $items[] = [
                    'name' => $module->getName(),
                    'status' => isset($enabled[$module->getName()]) ? 'enabled' : 'disabled',
                    'description' => $module->getDescription(),
                    'priority' => $module->getPriority(),
                ];
            }

            return [
                'count' => count($all),
                'enabled' => count($enabled),
                'disabled' => count($disabled),
                'items' => $items,
            ];
        } catch (\Throwable) {
            return ['count' => 0, 'enabled' => 0, 'disabled' => 0, 'items' => []];
        }
    }

    /**
     * @return array{name: string, version: string, template: string}
     */
    public function collectThemeInfo(): array
    {
        if (! function_exists('wp_get_theme')) {
            return ['name' => 'Unknown', 'version' => 'Unknown', 'template' => 'Unknown'];
        }

        $theme = wp_get_theme();

        return [
            'name' => (string) $theme->get('Name'),
            'version' => (string) $theme->get('Version'),
            'template' => (string) $theme->get_template(),
        ];
    }
}
