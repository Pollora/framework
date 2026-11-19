<?php

declare(strict_types=1);

namespace Pollora\VersionCheck\UI\Http;

use Illuminate\Support\Facades\Request;
use Pollora\VersionCheck\Domain\Services\VersionComparator;
use Pollora\VersionCheck\Infrastructure\Providers\VersionCheckServiceProvider;

/**
 * Renders a dismissable WordPress admin notice when a Pollora update is available.
 *
 * The notice is displayed as a warning banner in the WordPress admin area.
 * Users can dismiss it via the standard WordPress dismiss button, which triggers
 * an AJAX request storing the dismissed version in user meta. The notice will
 * reappear when a newer version becomes available.
 *
 * @see VersionCheckServiceProvider
 */
class AdminNotice
{
    /** @var string User meta key storing the version that was dismissed */
    private const string DISMISS_META_KEY = 'pollora_dismissed_update_version';

    /**
     * Capability required to see and dismiss the notice: the framework version and
     * the update state are site-administration information, not for every logged-in user.
     *
     * Public because the AJAX action that dismisses the notice declares it
     * (see {@see VersionCheckServiceProvider::boot()}).
     */
    public const string CAPABILITY = 'manage_options';

    /**
     * WordPress nonce action for the dismiss AJAX request: the notice creates the nonce with it
     * and the AJAX action declares it with `requireNonce()`.
     */
    public const string NONCE_ACTION = 'pollora_dismiss_update';

    public function __construct(
        private readonly VersionComparator $comparator
    ) {}

    /**
     * Render the update notice in the WordPress admin area.
     *
     * Outputs a warning notice with the available version, a link to the
     * changelog, and inline JavaScript to handle dismissal via AJAX.
     * Does nothing if the user lacks the `manage_options` capability, if no update
     * is available or if the notice was already dismissed.
     */
    public function render(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            return;
        }

        if (! $this->comparator->isUpdateAvailable()) {
            return;
        }

        $latest = $this->comparator->getLatestVersion();

        if ($this->isDismissed($latest)) {
            return;
        }

        $current = $this->comparator->getCurrentVersion();
        $nonce = wp_create_nonce(self::NONCE_ACTION);
        $changelogUrl = 'https://github.com/Pollora/framework/releases/tag/v'.$latest;

        /* translators: 1: latest version, 2: current version, 3: changelog URL */
        $message = sprintf(
            __('<strong>Pollora %1$s</strong> is available (you are using %2$s). <a href="%3$s" target="_blank" rel="noopener noreferrer">View changelog</a>.', 'pollora'),
            esc_html($latest),
            esc_html($current ?? __('unknown', 'pollora')),
            esc_url($changelogUrl)
        );

        printf(
            '<div class="notice notice-warning is-dismissible pollora-update-notice" data-version="%s" data-nonce="%s">'
            .'<p>%s</p>'
            .'</div>'
            .'<script>jQuery(function($){'
            .'$(document).on("click",".pollora-update-notice .notice-dismiss",function(){'
            .'var $n=$(this).closest(".pollora-update-notice");'
            .'$.post(ajaxurl,{action:"pollora_dismiss_update_notice",version:$n.data("version"),_wpnonce:$n.data("nonce")});'
            .'});'
            .'});</script>',
            esc_attr($latest),
            esc_attr($nonce),
            $message
        );
    }

    /**
     * Handle the AJAX request to dismiss the update notice.
     *
     * Stores the dismissed version in user meta so the notice is not shown again until a newer
     * version is released.
     *
     * The request is checked before this runs: the AJAX action declared in
     * {@see VersionCheckServiceProvider::boot()} requires the {@see self::NONCE_ACTION} nonce (a request
     * without it gets a 403) and the {@see self::CAPABILITY} capability (a user without it gets a 403,
     * a guest a 401; the nonce alone only proves the request comes from a page the user loaded).
     * Do not register this method on `wp_ajax_*` by hand: it would run unchecked.
     */
    public function dismiss(): void
    {
        $version = sanitize_text_field(Request::post('version') ?? '');

        if ($version !== '') {
            update_user_meta(get_current_user_id(), self::DISMISS_META_KEY, $version);
        }

        wp_die();
    }

    /**
     * Check whether the user has dismissed the notice for the given version.
     *
     * @param  string|null  $version  The version to check against the dismissed version
     * @return bool True if the notice was dismissed for this exact version
     */
    private function isDismissed(?string $version): bool
    {
        if ($version === null) {
            return false;
        }

        $dismissed = get_user_meta(get_current_user_id(), self::DISMISS_META_KEY, true);

        return $dismissed === $version;
    }
}
