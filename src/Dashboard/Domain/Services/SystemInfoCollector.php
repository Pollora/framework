<?php

declare(strict_types=1);

namespace Pollora\Dashboard\Domain\Services;

use Illuminate\Foundation\Application;
use Illuminate\Support\Str;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\VersionCheck\Domain\Services\VersionComparator;
use Spatie\StructureDiscoverer\Cache\NullDiscoverCacheDriver;

/**
 * Collects system information about the Pollora framework and its environment.
 *
 * Gathers version data, discovery statistics (post types, taxonomies, hooks),
 * cache state, and environment details for display in the admin dashboard
 * and CLI status command.
 */
final class SystemInfoCollector
{
    public function __construct(
        private readonly VersionComparator $versionComparator,
        private readonly DiscoveryManager $discoveryManager,
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
            'discovery' => $this->collectDiscoveryInfo(),
            'cache' => $this->collectCacheInfo(),
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
            'laravel' => Application::VERSION,
            'wordpress' => $wpVersion,
        ];
    }

    /**
     * @return array{post_types: array{count: int, items: list<array{class: string, slug: string, label: string}>}, taxonomies: array{count: int, items: list<array{class: string, slug: string, label: string}>}, hooks: array{count: int, actions: int, filters: int}}
     */
    public function collectDiscoveryInfo(): array
    {
        return [
            'post_types' => $this->collectPostTypeInfo(),
            'taxonomies' => $this->collectTaxonomyInfo(),
            'hooks' => $this->collectHookInfo(),
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

            return [
                'count' => count($result),
                'items' => $result,
            ];
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

            return [
                'count' => count($result),
                'items' => $result,
            ];
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

            return [
                'count' => $actions + $filters,
                'actions' => $actions,
                'filters' => $filters,
            ];
        } catch (\Throwable) {
            return ['count' => 0, 'actions' => 0, 'filters' => 0];
        }
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
