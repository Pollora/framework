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

    /** @var string WordPress nonce action for dismiss AJAX request */
    private const string NONCE_ACTION = 'pollora_dismiss_update';

    public function __construct(
        private readonly VersionComparator $comparator
    ) {}

    /**
     * Render the update notice in the WordPress admin area.
     *
     * Outputs a warning notice with the available version, a link to the
     * changelog, and inline JavaScript to handle dismissal via AJAX.
     * Does nothing if no update is available or the notice was already dismissed.
     */
    public function render(): void
    {
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

        printf(
            '<div class="notice notice-warning is-dismissible pollora-update-notice" data-version="%s" data-nonce="%s">'
            .'<p><strong>Pollora %s</strong> is available (you are using %s). '
            .'<a href="%s" target="_blank" rel="noopener noreferrer">View changelog</a>.</p>'
            .'</div>'
            .'<script>jQuery(function($){'
            .'$(document).on("click",".pollora-update-notice .notice-dismiss",function(){'
            .'var $n=$(this).closest(".pollora-update-notice");'
            .'$.post(ajaxurl,{action:"pollora_dismiss_update_notice",version:$n.data("version"),_wpnonce:$n.data("nonce")});'
            .'});'
            .'});</script>',
            esc_attr($latest),
            esc_attr($nonce),
            esc_html($latest),
            esc_html($current ?? 'unknown'),
            esc_url($changelogUrl)
        );
    }

    /**
     * Handle the AJAX request to dismiss the update notice.
     *
     * Verifies the nonce, then stores the dismissed version in user meta
     * so the notice is not shown again until a newer version is released.
     * Called via the `wp_ajax_pollora_dismiss_update_notice` action.
     */
    public function dismiss(): void
    {
        check_ajax_referer(self::NONCE_ACTION);

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
