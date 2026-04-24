<?php

declare(strict_types=1);

namespace Pollora\Dashboard\UI\Http;

use Pollora\Dashboard\Domain\Services\SystemInfoCollector;

/**
 * Renders the Pollora admin dashboard page.
 *
 * Displays system information including framework version, environment details,
 * discovered entities, performance stats, and cache state, with Pollora branding.
 */
final readonly class DashboardController
{
    public function __construct(
        private SystemInfoCollector $collector
    ) {}

    public function __invoke(): void
    {
        $info = $this->collector->collect();

        echo '<div class="wrap pollora-wrap">';

        $this->renderStyles();
        $this->renderHeader($info['framework']);

        echo '<div class="pollora-dashboard">';

        $this->renderEnvironmentCard($info['environment']);
        $this->renderWordPressCard($info['wordpress']);
        $this->renderPostTypesCard($info['discovery']['post_types']);
        $this->renderTaxonomiesCard($info['discovery']['taxonomies']);
        $this->renderHooksCard($info['discovery']['hooks']);
        $this->renderRestRoutesCard($info['discovery']['rest_routes']);
        $this->renderWpCliCard($info['discovery']['wp_cli_commands']);
        $this->renderSchedulesCard($info['discovery']['schedules']);
        $this->renderServiceProvidersCard($info['discovery']['service_providers']);
        $this->renderModulesCard($info['modules']);
        $this->renderCacheCard($info['cache']);
        $this->renderPerformanceCard($info['performance']);
        $this->renderThemeCard($info['theme']);

        echo '</div>';
        echo '</div>';
    }

    private function renderStyles(): void
    {
        echo '<style>
            .pollora-wrap { max-width: 1200px; }
            .pollora-header {
                display: flex; align-items: center; gap: 20px;
                background: linear-gradient(135deg, #1d142a 0%, #2d1f3d 100%);
                border-radius: 8px; padding: 24px 32px; margin-bottom: 24px;
            }
            .pollora-header-logo { flex-shrink: 0; max-width: 200px; }
            .pollora-header-logo svg { width: 100%; height: auto; display: block; }
            .pollora-header-info { flex: 1; }
            .pollora-header .pollora-version {
                font-size: 13px; font-weight: 500; padding: 3px 10px; border-radius: 12px; display: inline-block;
            }
            .pollora-version--current {
                background: linear-gradient(135deg, #fb196d, #ff8c12); color: #fff;
            }
            .pollora-version--update {
                background: #fff3cd; color: #856404;
            }
            .pollora-dashboard {
                display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
                gap: 16px;
            }
            .pollora-card {
                background: #fff; border: 1px solid #e0e0e0; border-radius: 6px;
                padding: 20px; position: relative; overflow: hidden;
            }
            .pollora-card::before {
                content: ""; position: absolute; top: 0; left: 0; right: 0; height: 3px;
                background: linear-gradient(90deg, #fb196d, #ff314c, #ff5334, #ff8c12);
            }
            .pollora-card h2 {
                margin: 0 0 14px; padding: 0; font-size: 13px; font-weight: 600;
                color: #1d142a; text-transform: uppercase; letter-spacing: 0.5px;
            }
            .pollora-card table { width: 100%; border-collapse: collapse; }
            .pollora-card td { padding: 7px 0; border-bottom: 1px solid #f5f5f5; font-size: 13px; }
            .pollora-card tr:last-child td { border-bottom: none; }
            .pollora-card td:first-child { color: #666; width: 40%; }
            .pollora-card td:last-child { color: #1d142a; font-weight: 500; }
            .pollora-badge {
                display: inline-block; padding: 2px 10px; border-radius: 10px;
                font-size: 12px; font-weight: 600; line-height: 1.5;
            }
            .pollora-badge--gradient { background: linear-gradient(135deg, #fb196d, #ff8c12); color: #fff; }
            .pollora-badge--green { background: #e8f5e9; color: #2e7d32; }
            .pollora-badge--orange { background: #fff3e0; color: #e65100; }
            .pollora-badge--gray { background: #f5f5f5; color: #616161; }
            .pollora-entity-list { margin: 0; padding: 0; list-style: none; }
            .pollora-entity-item {
                display: flex; align-items: center; justify-content: space-between;
                padding: 8px 0; border-bottom: 1px solid #f5f5f5; gap: 8px;
            }
            .pollora-entity-item:last-child { border-bottom: none; }
            .pollora-entity-label { font-size: 13px; font-weight: 500; color: #1d142a; }
            .pollora-entity-meta { font-size: 11px; color: #999; word-break: break-all; }
            .pollora-entity-slug {
                font-size: 11px; background: #f5f5f5; color: #666;
                padding: 2px 8px; border-radius: 3px; font-family: monospace; white-space: nowrap; flex-shrink: 0;
            }
            .pollora-empty { color: #999; font-style: italic; font-size: 13px; }
        </style>';
    }

    /**
     * @param  array{current: ?string, latest: ?string, update_available: bool}  $framework
     */
    private function renderHeader(array $framework): void
    {
        $current = $framework['current'] ?? __('Unknown', 'pollora');
        $updateAvailable = $framework['update_available'];
        $isDev = is_string($current) && str_starts_with($current, 'dev-');

        $logoPath = dirname(__DIR__, 4).'/resources/images/pollora-logo.svg';

        echo '<div class="pollora-header">';

        if (file_exists($logoPath)) {
            echo '<div class="pollora-header-logo">';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted SVG from framework package
            echo file_get_contents($logoPath);
            echo '</div>';
        }

        echo '<div class="pollora-header-info">';

        if ($isDev) {
            printf(
                '<span class="pollora-version pollora-version--current">%s</span>',
                esc_html((string) $current)
            );
        } elseif ($updateAvailable) {
            $latest = $framework['latest'] ?? '';
            printf(
                '<span class="pollora-version pollora-version--update">v%s &rarr; v%s %s</span>',
                esc_html((string) $current),
                esc_html($latest),
                __('available', 'pollora')
            );
        } else {
            printf(
                '<span class="pollora-version pollora-version--current">v%s</span>',
                esc_html((string) $current)
            );
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * @param  array{php: string, laravel: string, wordpress: string}  $environment
     */
    private function renderEnvironmentCard(array $environment): void
    {
        echo '<div class="pollora-card">';
        echo '<h2>'.__('Environment', 'pollora').'</h2>';
        echo '<table>';
        printf('<tr><td>PHP</td><td>%s</td></tr>', esc_html($environment['php']));
        printf('<tr><td>Laravel</td><td>%s</td></tr>', esc_html($environment['laravel']));
        printf('<tr><td>WordPress</td><td>%s</td></tr>', esc_html($environment['wordpress']));
        echo '</table>';
        echo '</div>';
    }

    /**
     * @param  array{debug: bool, multisite: bool, permalink_structure: string}  $wordpress
     */
    private function renderWordPressCard(array $wordpress): void
    {
        $debugBadge = $wordpress['debug']
            ? '<span class="pollora-badge pollora-badge--orange">'.__('Enabled', 'pollora').'</span>'
            : '<span class="pollora-badge pollora-badge--green">'.__('Disabled', 'pollora').'</span>';

        $multisiteBadge = $wordpress['multisite']
            ? '<span class="pollora-badge pollora-badge--gradient">'.__('Yes', 'pollora').'</span>'
            : '<span class="pollora-badge pollora-badge--gray">'.__('No', 'pollora').'</span>';

        echo '<div class="pollora-card">';
        echo '<h2>'.__('WordPress Config', 'pollora').'</h2>';
        echo '<table>';
        printf('<tr><td>WP_DEBUG</td><td>%s</td></tr>', $debugBadge);
        printf('<tr><td>%s</td><td>%s</td></tr>', __('Multisite', 'pollora'), $multisiteBadge);
        printf('<tr><td>%s</td><td><code>%s</code></td></tr>', __('Permalinks', 'pollora'), esc_html($wordpress['permalink_structure']));
        echo '</table>';
        echo '</div>';
    }

    /**
     * @param  array{count: int, items: list<array{class: string, slug: string, label: string}>}  $postTypes
     */
    private function renderPostTypesCard(array $postTypes): void
    {
        $this->renderEntityCard(__('Post Types', 'pollora'), $postTypes, true);
    }

    /**
     * @param  array{count: int, items: list<array{class: string, slug: string, label: string}>}  $taxonomies
     */
    private function renderTaxonomiesCard(array $taxonomies): void
    {
        $this->renderEntityCard(__('Taxonomies', 'pollora'), $taxonomies, true);
    }

    /**
     * @param  array{count: int, items: list<array{class: string, slug?: string, label?: string, method?: string}>}  $data
     */
    private function renderEntityCard(string $title, array $data, bool $hasSlugAndLabel = false): void
    {
        echo '<div class="pollora-card">';
        printf(
            '<h2>%s <span class="pollora-badge pollora-badge--gradient">%d</span></h2>',
            $title,
            $data['count']
        );

        if ($data['items'] === []) {
            printf('<p class="pollora-empty">%s</p>', __('None discovered.', 'pollora'));
        } else {
            echo '<ul class="pollora-entity-list">';
            foreach ($data['items'] as $item) {
                echo '<li class="pollora-entity-item">';
                echo '<div>';

                if ($hasSlugAndLabel && isset($item['label'])) {
                    printf('<div class="pollora-entity-label">%s</div>', esc_html($item['label']));
                }

                $meta = $item['class'];
                if (isset($item['method'])) {
                    $meta .= '::'.$item['method'].'()';
                }

                printf('<div class="pollora-entity-meta">%s</div>', esc_html($meta));

                echo '</div>';

                if ($hasSlugAndLabel && isset($item['slug'])) {
                    printf('<span class="pollora-entity-slug">%s</span>', esc_html($item['slug']));
                }

                echo '</li>';
            }

            echo '</ul>';
        }

        echo '</div>';
    }

    /**
     * @param  array{count: int, actions: int, filters: int}  $hooks
     */
    private function renderHooksCard(array $hooks): void
    {
        echo '<div class="pollora-card">';
        printf(
            '<h2>%s <span class="pollora-badge pollora-badge--gradient">%d</span></h2>',
            __('Hooks', 'pollora'),
            $hooks['count']
        );
        echo '<table>';
        printf('<tr><td>%s</td><td>%d</td></tr>', __('Actions', 'pollora'), $hooks['actions']);
        printf('<tr><td>%s</td><td>%d</td></tr>', __('Filters', 'pollora'), $hooks['filters']);
        echo '</table>';
        echo '</div>';
    }

    /**
     * @param  array{count: int, items: list<array{class: string}>}  $restRoutes
     */
    private function renderRestRoutesCard(array $restRoutes): void
    {
        $this->renderEntityCard(__('REST API Routes', 'pollora'), $restRoutes);
    }

    /**
     * @param  array{count: int, items: list<array{class: string}>}  $wpCli
     */
    private function renderWpCliCard(array $wpCli): void
    {
        $this->renderEntityCard(__('WP-CLI Commands', 'pollora'), $wpCli);
    }

    /**
     * @param  array{count: int, items: list<array{class: string, method: string}>}  $schedules
     */
    private function renderSchedulesCard(array $schedules): void
    {
        $this->renderEntityCard(__('Scheduled Tasks', 'pollora'), $schedules);
    }

    /**
     * @param  array{count: int, items: list<array{class: string}>}  $providers
     */
    private function renderServiceProvidersCard(array $providers): void
    {
        $this->renderEntityCard(__('Auto-discovered Providers', 'pollora'), $providers);
    }

    /**
     * @param  array{count: int, enabled: int, disabled: int, items: list<array{name: string, status: string, description: string, priority: string}>}  $modules
     */
    private function renderModulesCard(array $modules): void
    {
        echo '<div class="pollora-card">';
        printf(
            '<h2>%s <span class="pollora-badge pollora-badge--gradient">%d</span></h2>',
            __('Modules', 'pollora'),
            $modules['count']
        );

        if ($modules['items'] === []) {
            printf('<p class="pollora-empty">%s</p>', __('No modules installed.', 'pollora'));
        } else {
            echo '<table>';
            printf(
                '<tr><td>%s</td><td><span class="pollora-badge pollora-badge--green">%d</span></td></tr>',
                __('Enabled', 'pollora'),
                $modules['enabled']
            );
            printf(
                '<tr><td>%s</td><td><span class="pollora-badge pollora-badge--gray">%d</span></td></tr>',
                __('Disabled', 'pollora'),
                $modules['disabled']
            );
            echo '</table>';

            echo '<ul class="pollora-entity-list" style="margin-top: 10px;">';
            foreach ($modules['items'] as $module) {
                $statusBadge = $module['status'] === 'enabled'
                    ? '<span class="pollora-badge pollora-badge--green">'.__('On', 'pollora').'</span>'
                    : '<span class="pollora-badge pollora-badge--gray">'.__('Off', 'pollora').'</span>';

                echo '<li class="pollora-entity-item">';
                echo '<div>';
                printf('<div class="pollora-entity-label">%s</div>', esc_html($module['name']));
                if ($module['description'] !== '') {
                    printf('<div class="pollora-entity-meta">%s</div>', esc_html($module['description']));
                }

                echo '</div>';
                echo $statusBadge;
                echo '</li>';
            }

            echo '</ul>';
        }

        echo '</div>';
    }

    /**
     * @param  array{driver: string, enabled: bool}  $cache
     */
    private function renderCacheCard(array $cache): void
    {
        $statusBadge = $cache['enabled']
            ? '<span class="pollora-badge pollora-badge--green">'.__('Enabled', 'pollora').'</span>'
            : '<span class="pollora-badge pollora-badge--gray">'.__('Disabled', 'pollora').'</span>';

        echo '<div class="pollora-card">';
        echo '<h2>'.__('Discovery Cache', 'pollora').'</h2>';
        echo '<table>';
        printf('<tr><td>%s</td><td>%s</td></tr>', __('Driver', 'pollora'), esc_html($cache['driver']));
        printf('<tr><td>%s</td><td>%s</td></tr>', __('Status', 'pollora'), $statusBadge);
        echo '</table>';
        echo '</div>';
    }

    /**
     * @param  array<string, mixed>  $performance
     */
    private function renderPerformanceCard(array $performance): void
    {
        echo '<div class="pollora-card">';
        echo '<h2>'.__('Discovery Performance', 'pollora').'</h2>';

        if ($performance === []) {
            printf('<p class="pollora-empty">%s</p>', __('No performance data available.', 'pollora'));
        } else {
            echo '<table>';

            $context = $performance['context'] ?? [];
            if (isset($context['cache_hits'])) {
                printf('<tr><td>%s</td><td>%d</td></tr>', __('Cache hits', 'pollora'), $context['cache_hits']);
            }

            if (isset($context['cache_misses'])) {
                printf('<tr><td>%s</td><td>%d</td></tr>', __('Cache misses', 'pollora'), $context['cache_misses']);
            }

            if (isset($context['classes_processed'])) {
                printf('<tr><td>%s</td><td>%d</td></tr>', __('Classes processed', 'pollora'), $context['classes_processed']);
            }

            if (isset($context['discoveries_executed'])) {
                printf('<tr><td>%s</td><td>%d</td></tr>', __('Discoveries executed', 'pollora'), $context['discoveries_executed']);
            }

            if (isset($performance['instance_pool']['total_instances'])) {
                printf('<tr><td>%s</td><td>%d</td></tr>', __('Instance pool size', 'pollora'), $performance['instance_pool']['total_instances']);
            }

            if (isset($performance['static_cache_size'])) {
                printf('<tr><td>%s</td><td>%d</td></tr>', __('Static cache entries', 'pollora'), $performance['static_cache_size']);
            }

            echo '</table>';
        }

        echo '</div>';
    }

    /**
     * @param  array{name: string, version: string, template: string}  $theme
     */
    private function renderThemeCard(array $theme): void
    {
        echo '<div class="pollora-card">';
        echo '<h2>'.__('Active Theme', 'pollora').'</h2>';
        echo '<table>';
        printf('<tr><td>%s</td><td>%s</td></tr>', __('Name', 'pollora'), esc_html($theme['name']));
        printf('<tr><td>%s</td><td>%s</td></tr>', __('Version', 'pollora'), esc_html($theme['version']));
        printf('<tr><td>%s</td><td><code>%s</code></td></tr>', __('Template', 'pollora'), esc_html($theme['template']));
        echo '</table>';
        echo '</div>';
    }
}
