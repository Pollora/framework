<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Installation;

use DB;
use Pollora\Services\WordPress\Installation\DTO\InstallationConfig;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

/**
 * Service for handling WordPress core installation.
 *
 * This service manages the WordPress installation process, including database setup,
 * core installation, and initial configuration of the site.
 */
class InstallationService
{
    /**
     * Create a new installation service instance.
     *
     * @param  WordPressInstallLoaderService  $installLoaderService  Service for loading WordPress core files
     */
    public function __construct(
        private readonly WordPressInstallLoaderService $installLoaderService,
    ) {}

    /**
     * Check if WordPress is already installed.
     *
     * Verifies the presence of essential WordPress options in the database.
     *
     * @return bool True if WordPress is installed, false otherwise
     *
     * @throws \Exception When database connection fails
     */
    public function isInstalled(): bool
    {
        try {
            return DB::table('options')
                ->whereIn('option_name', ['siteurl', 'home', 'blogname'])
                ->count() === 3;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Install WordPress with the provided configuration.
     *
     * Performs the core WordPress installation and applies additional configuration.
     * This includes setting up the database, creating admin user, and configuring
     * initial site settings.
     *
     * @param  InstallationConfig  $config  Configuration object containing installation parameters
     *
     * @throws WordPressInstallationException If installation fails or configuration is invalid
     */
    public function install(InstallationConfig $config): void
    {
        $this->installLoaderService->bootstrap();

        $result = spin(
            callback: fn () => wp_install(
                $config->title,
                $config->adminUser,
                $config->adminEmail,
                $config->isPublic,
                '',
                $config->adminPassword,
                $config->locale
            ),
            message: 'Installing WordPress...'
        );

        if (is_wp_error($result)) {
            throw new WordPressInstallationException($result->get_error_message());
        }

        $this->configureInstallation($config);
    }

    /**
     * Configure additional WordPress installation settings.
     *
     * Sets up additional options like site description and privacy settings
     * after the core installation is complete.
     *
     * @param  InstallationConfig  $config  Configuration object containing site settings
     *
     * @throws WordPressInstallationException If upload directory setup fails
     */
    private function configureInstallation(InstallationConfig $config): void
    {
        update_option('blogdescription', $config->description);
        update_option('blog_public', $config->isPublic ? '1' : '0');

        // Enable pretty permalinks by default
        if (function_exists('update_option')) {
            update_option('permalink_structure', '/%postname%/');

            // The bootstrap filter on 'pre_option_permalink_structure' forces an empty
            // structure. Replace it so flush_rewrite_rules() uses the actual value.
            remove_all_filters('pre_option_permalink_structure');

            // Reinitialize WP_Rewrite with the correct permalink structure
            global $wp_rewrite;
            $wp_rewrite->init();

            // Drop the rules rather than flush them. The set WordPress can
            // build at this point is incomplete — taxonomies and post types are
            // not all registered yet — and flushing stores exactly that, which
            // is why a freshly installed site answered 404 on every article
            // until something flushed again later. Deleting the option lets
            // WordPress rebuild it on the first request, once everything is
            // registered. Same reasoning as completeWebInstall(), which fixes
            // the web installer; that hook is only registered outside the
            // console, so the CLI install never went through it.
            if (function_exists('delete_option')) {
                delete_option('rewrite_rules');
            }

            info('Pretty permalinks have been enabled by default.');
        } else {
            info('Permalink structure could not be set automatically. Ensure WordPress is loaded.');
        }

        $uploadDir = wp_upload_dir();
        if (! empty($uploadDir['error'])) {
            throw new WordPressInstallationException($uploadDir['error']);
        }
    }
}
