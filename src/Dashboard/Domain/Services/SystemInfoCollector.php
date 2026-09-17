<?php

declare(strict_types=1);

namespace Pollora\Dashboard\Domain\Services;

use Nwidart\Modules\Contracts\RepositoryInterface;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Support\Domain\StringHelper;
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
            'translations' => $this->collectTranslationInfo(),
        ];
    }

    /**
     * Report whether the `__()` override is the one actually installed.
     *
     * `laravel/framework` declares `__()` behind the same `function_exists()`
     * guard as `pollora/helper-overrider`, and Composer emits `autoload.files`
     * in dependency order — so whichever file it loads first wins. When Laravel
     * wins, nothing errors: WordPress catalogues simply stop resolving and every
     * core, theme and plugin string silently renders untranslated. There is no
     * other signal for it, which is why it is surfaced here.
     *
     * @return array{override_active: bool, helper_file: string, wordpress_locale: string, laravel_locale: string}
     */
    public function collectTranslationInfo(): array
    {
        $wordpressLocale = function_exists('get_locale') && function_exists('wp_cache_get')
            ? (string) get_locale()
            : 'Unknown';

        return [
            'override_active' => $this->isTranslationOverrideActive(),
            'helper_file' => $this->translationHelperFile() ?? 'Unknown',
            'wordpress_locale' => $wordpressLocale !== '' ? $wordpressLocale : 'Unknown',
            'laravel_locale' => $this->collectLaravelLocale(),
        ];
    }

    /**
     * Whether `__()` resolves to Pollora's override rather than Laravel's.
     *
     * The override and its resolver factory are declared in the same
     * `helpers.php`, so matching file names prove the package won the race.
     * Comparing files rather than matching on a vendor path keeps this working
     * whatever the install layout.
     */
    private function isTranslationOverrideActive(): bool
    {
        $helperFile = $this->translationHelperFile();

        if ($helperFile === null || ! function_exists('pollora_translation_resolver')) {
            return false;
        }

        try {
            return (new \ReflectionFunction('pollora_translation_resolver'))->getFileName() === $helperFile;
        } catch (\ReflectionException) {
            return false;
        }
    }

    /**
     * The file declaring the active `__()`, or null when it cannot be resolved.
     */
    private function translationHelperFile(): ?string
    {
        if (! function_exists('__')) {
            return null;
        }

        try {
            $file = (new \ReflectionFunction('__'))->getFileName();
        } catch (\ReflectionException) {
            return null;
        }

        return $file === false ? null : $file;
    }

    /**
     * The locale Laravel's translator reports, which may differ from WordPress's.
     */
    private function collectLaravelLocale(): string
    {
        try {
            $translator = $this->container->get('translator');
        } catch (\Throwable) {
            return 'Unknown';
        }

        if (! is_object($translator) || ! method_exists($translator, 'getLocale')) {
            return 'Unknown';
        }

        $locale = $translator->getLocale();

        return is_string($locale) && $locale !== '' ? $locale : 'Unknown';
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
                $slug = StringHelper::kebab(class_basename($class));
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
                $slug = StringHelper::kebab(class_basename($class));
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

        return StringHelper::headline(class_basename($class));
    }

    private function getTaxonomyLabel(string $slug, string $class): string
    {
        if (function_exists('get_taxonomy')) {
            $object = get_taxonomy($slug);

            if ($object !== false && isset($object->labels->name)) {
                return $object->labels->name;
            }
        }

        return StringHelper::headline(class_basename($class));
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
