<?php

declare(strict_types=1);

namespace Pollora\Modules\UI\Http;

use Illuminate\Support\Facades\Request;
use Pollora\Modules\Application\Services\ModuleStates;
use Pollora\Modules\Infrastructure\Activation\JsonStateConnector;
use RuntimeException;

/**
 * The Modules view of the Plugins screen: plugins.php?page=pollora-modules.
 *
 * A module has no plugin file for WordPress to load, so it never appears among
 * WordPress's own rows: a "Modules (n)" link joins the plugin views and leads
 * to this page, which lists modules the way the plugin list does and switches
 * them through nwidart/laravel-modules.
 */
class ModulesAdminPage
{
    public const string SLUG = 'pollora-modules';

    private const string NONCE = 'pollora-modules';

    public function __construct(private readonly ModuleStates $states) {}

    public function url(): string
    {
        return admin_url('plugins.php?page='.self::SLUG);
    }

    /**
     * Add the page under Plugins.
     */
    public function addMenuPage(): void
    {
        if (! $this->states->available()) {
            return;
        }

        add_submenu_page(
            'plugins.php',
            __('Modules', 'pollora'),
            __('Modules', 'pollora'),
            $this->states->capability(),
            self::SLUG,
            $this->render(...),
        );
    }

    /**
     * Add "Modules (n)" to the plugin views (All, Active, Must-Use…).
     *
     * @param  array<string, string>  $views
     * @return array<string, string>
     */
    public function addView(array $views): array
    {
        if (! $this->states->available() || ! current_user_can($this->states->capability())) {
            return $views;
        }

        $views[self::SLUG] = sprintf(
            '<a href="%s">%s <span class="count">(%d)</span></a>',
            esc_url($this->url()),
            esc_html__('Modules', 'pollora'),
            count($this->states->all()),
        );

        return $views;
    }

    /**
     * Handle a switch posted from the page and redirect, on load-plugins_page_pollora-modules.
     */
    public function handleRequest(): void
    {
        if ((Request::server('REQUEST_METHOD') ?? 'GET') !== 'POST') {
            return;
        }

        $this->saveNotice($this->switchFromRequest(Request::post()));

        wp_safe_redirect($this->url());
        exit;
    }

    /**
     * Switch the modules a request names: a row button (`toggle` = "enable:Crm")
     * or a bulk action over `modules[]`.
     *
     * @param  array<string, mixed>  $request
     * @return array{type: string, messages: list<string>}
     */
    public function switchFromRequest(array $request): array
    {
        check_admin_referer(self::NONCE);

        if (! current_user_can($this->states->capability())) {
            return ['type' => 'error', 'messages' => [__('You are not allowed to switch modules.', 'pollora')]];
        }

        [$action, $modules] = $this->requestedSwitch($request);

        if ($action === null || $modules === []) {
            return ['type' => 'warning', 'messages' => [__('Select a module and an action.', 'pollora')]];
        }

        $switched = [];
        $errors = [];

        foreach ($modules as $module) {
            try {
                $this->states->switch($module, $action === 'enable');
                $switched[] = $module;
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        $messages = [];

        if ($switched !== []) {
            $messages[] = sprintf(
                $action === 'enable'
                    ? __('%s enabled. The change applies from the next request.', 'pollora')
                    : __('%s disabled. The change applies from the next request.', 'pollora'),
                implode(', ', $switched),
            );
        }

        return ['type' => $errors === [] ? 'success' : ($switched === [] ? 'error' : 'warning'), 'messages' => [...$messages, ...$errors]];
    }

    public function render(): void
    {
        $modules = $this->states->all();
        $connector = $this->states->connector();

        echo '<div class="wrap">';
        printf('<h1 class="wp-heading-inline">%s</h1><hr class="wp-header-end">', esc_html__('Plugins', 'pollora'));

        $this->renderNotice();

        printf(
            '<ul class="subsubsub"><li><a href="%s">%s</a> |</li><li><a href="%s" class="current" aria-current="page">%s <span class="count">(%d)</span></a></li></ul>',
            esc_url(admin_url('plugins.php')),
            esc_html__('All plugins', 'pollora'),
            esc_url($this->url()),
            esc_html__('Modules', 'pollora'),
            count($modules),
        );
        echo '<div class="clear"></div>';

        $this->renderConnectorNotice($connector->label(), $connector instanceof JsonStateConnector ? basename($connector->path()) : null, $connector->persistent(), $connector->writable());

        $confirm = $connector->persistent() ? '' : sprintf(
            ' onclick="return confirm(%s)"',
            esc_attr((string) wp_json_encode(__('The next deployment resets this change. Switch the module anyway?', 'pollora'))),
        );

        printf('<form method="post" action="%s">', esc_url($this->url()));
        wp_nonce_field(self::NONCE);

        $this->renderBulkActions($confirm);

        echo '<table class="wp-list-table widefat plugins"><thead><tr>';
        echo '<td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-1"></td>';
        printf('<th scope="col" class="manage-column column-name column-primary">%s</th>', esc_html__('Module', 'pollora'));
        printf('<th scope="col" class="manage-column column-description">%s</th>', esc_html__('Description', 'pollora'));
        printf('<th scope="col" class="manage-column">%s</th>', esc_html__('State', 'pollora'));
        printf('<th scope="col" class="manage-column">%s</th>', esc_html__('Connector', 'pollora'));
        echo '</tr></thead><tbody id="the-list">';

        if ($modules === []) {
            printf('<tr class="no-items"><td class="colspanchange" colspan="5">%s</td></tr>', esc_html__('No modules found.', 'pollora'));
        }

        foreach ($modules as $module) {
            $this->renderRow($module, $connector->label(), $confirm);
        }

        echo '</tbody></table></form></div>';
    }

    /**
     * @param  array{name: string, description: string, path: string, enabled: bool, locked: bool|null, toggleable: bool, reason: string|null}  $module
     */
    private function renderRow(array $module, string $connectorLabel, string $confirm): void
    {
        $name = $module['name'];

        printf('<tr class="%s" data-module="%s">', $module['enabled'] ? 'active' : 'inactive', esc_attr($name));
        printf(
            '<th scope="row" class="check-column"><input type="checkbox" name="modules[]" value="%s"%s></th>',
            esc_attr($name),
            $module['toggleable'] ? '' : ' disabled',
        );

        echo '<td class="plugin-title column-primary">';
        printf('<strong>%s</strong>', esc_html($name));
        echo '<div class="row-actions visible">';

        if ($module['toggleable']) {
            printf(
                '<span class="%1$s"><button type="submit" name="toggle" value="%2$s:%3$s" class="button-link"%5$s>%4$s</button></span>',
                $module['enabled'] ? 'deactivate' : 'activate',
                $module['enabled'] ? 'disable' : 'enable',
                esc_attr($name),
                $module['enabled'] ? esc_html__('Disable', 'pollora') : esc_html__('Enable', 'pollora'),
                $confirm,
            );
        } else {
            printf('<span class="description">%s</span>', esc_html((string) $module['reason']));
        }

        echo '</div></td>';

        printf(
            '<td class="column-description desc"><div class="plugin-description"><p>%s</p></div><div class="second plugin-version-author-uri"><code>%s</code></div></td>',
            esc_html($module['description']),
            esc_html($this->relativePath($module['path'])),
        );

        $state = match (true) {
            $module['locked'] === true => __('Locked enabled', 'pollora'),
            $module['locked'] === false => __('Locked disabled', 'pollora'),
            $module['enabled'] => __('Enabled', 'pollora'),
            default => __('Disabled', 'pollora'),
        };

        printf('<td>%s</td><td>%s</td></tr>', esc_html($state), esc_html($connectorLabel));
    }

    private function renderBulkActions(string $confirm): void
    {
        if (! $this->states->togglesEnabled()) {
            return;
        }

        echo '<div class="tablenav top"><div class="alignleft actions bulkactions">';
        printf('<label for="bulk-action-selector-top" class="screen-reader-text">%s</label>', esc_html__('Select bulk action', 'pollora'));
        echo '<select name="bulk_action" id="bulk-action-selector-top">';
        printf('<option value="">%s</option>', esc_html__('Bulk actions', 'pollora'));
        printf('<option value="enable">%s</option>', esc_html__('Enable', 'pollora'));
        printf('<option value="disable">%s</option>', esc_html__('Disable', 'pollora'));
        echo '</select>';
        printf('<input type="submit" class="button action" value="%s"%s>', esc_attr__('Apply', 'pollora'), $confirm);
        echo '</div><br class="clear"></div>';
    }

    private function renderConnectorNotice(string $label, ?string $file, bool $persistent, bool $writable): void
    {
        if (! $this->states->togglesEnabled()) {
            printf('<div class="notice notice-info inline"><p>%s</p></div>', esc_html__('Modules cannot be switched from the admin on this site (MODULES_ADMIN_TOGGLE=false). Use php artisan module:enable or module:disable.', 'pollora'));

            return;
        }

        if (! $writable) {
            printf(
                '<div class="notice notice-info inline"><p>%s</p></div>',
                esc_html(sprintf(
                    __('Module states are stored in %s, which cannot be written here. Commit the states with the code, or switch to the database connector: php artisan pollora:module:connector database --import.', 'pollora'),
                    $file ?? $label,
                )),
            );

            return;
        }

        if (! $persistent) {
            printf(
                '<div class="notice notice-warning inline"><p>%s</p></div>',
                esc_html(sprintf(__('Stored in %s — the next deployment resets it.', 'pollora'), $file ?? $label)),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array{0: 'enable'|'disable'|null, 1: list<string>}
     */
    private function requestedSwitch(array $request): array
    {
        $toggle = isset($request['toggle']) ? sanitize_text_field(wp_unslash((string) $request['toggle'])) : '';

        if (preg_match('/^(enable|disable):(.+)$/', $toggle, $matches) === 1) {
            return [$matches[1], [$matches[2]]];
        }

        $action = isset($request['bulk_action']) ? sanitize_key((string) $request['bulk_action']) : '';
        $modules = array_values(array_filter(array_map(
            fn (mixed $module): string => sanitize_text_field(wp_unslash((string) $module)),
            (array) ($request['modules'] ?? []),
        )));

        return [in_array($action, ['enable', 'disable'], true) ? $action : null, $modules];
    }

    /**
     * @param  array{type: string, messages: list<string>}  $notice
     */
    private function saveNotice(array $notice): void
    {
        set_transient($this->noticeKey(), $notice, 60);
    }

    private function renderNotice(): void
    {
        $notice = get_transient($this->noticeKey());

        if (! is_array($notice)) {
            return;
        }

        delete_transient($this->noticeKey());

        printf('<div class="notice notice-%s is-dismissible">', esc_attr((string) $notice['type']));

        foreach ((array) $notice['messages'] as $message) {
            printf('<p>%s</p>', esc_html((string) $message));
        }

        echo '</div>';
    }

    private function noticeKey(): string
    {
        return 'pollora_modules_notice_'.get_current_user_id();
    }

    private function relativePath(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), '/');
    }
}
