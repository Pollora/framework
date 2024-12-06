<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Installation;

use DB;
use Pollora\Services\WordPress\Installation\DTO\InstallationConfig;

use function Laravel\Prompts\spin;

class InstallationService
{
    public function __construct(
        private readonly WordPressInstallLoaderService $installLoaderService,
    ) {}

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

    public function install(InstallationConfig $config): void
    {
        $this->installLoaderService->bootstrap();

        $result = spin(
            message: 'Installing WordPress...',
            callback: fn () => wp_install(
                $config->title,
                $config->adminUser,
                $config->adminEmail,
                $config->isPublic,
                '',
                $config->adminPassword,
                $config->locale
            )
        );

        if (is_wp_error($result)) {
            throw new WordPressInstallationException($result->get_error_message());
        }

        $this->configureInstallation($config);
    }

    private function configureInstallation(InstallationConfig $config): void
    {
        update_option('blogdescription', $config->description);
        update_option('blog_public', $config->isPublic ? '1' : '0');

        $uploadDir = wp_upload_dir();
        if (! empty($uploadDir['error'])) {
            throw new WordPressInstallationException($uploadDir['error']);
        }
    }
}
