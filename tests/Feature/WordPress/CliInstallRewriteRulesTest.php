<?php

declare(strict_types=1);

use Pollora\Services\WordPress\Installation\DTO\InstallationConfig;
use Pollora\Services\WordPress\Installation\InstallationService;
use Pollora\Services\WordPress\Installation\WordPressInstallLoaderService;

/**
 * What `pollora:install` leaves behind for the rewrite rules.
 *
 * The set WordPress can build during an install is incomplete — taxonomies and
 * post types are not all registered at that point — so storing it leaves the
 * site answering 404 on every article until something flushes again later.
 * completeWebInstall() already drops the option for the web installer, but that
 * hook is only registered outside the console, so the CLI install went through
 * this service instead and flushed.
 */
beforeEach(function (): void {
    $this->service = new InstallationService(
        Mockery::mock(WordPressInstallLoaderService::class)
    );

    $this->config = new InstallationConfig(
        title: 'Test',
        description: 'Test site',
        adminUser: 'admin',
        adminEmail: 'admin@example.com',
        adminPassword: 'password',
        locale: 'en_US',
        isPublic: false,
    );

    $GLOBALS['wp_rewrite'] = new class
    {
        public bool $initialised = false;

        public function init(): void
        {
            $this->initialised = true;
        }
    };
});

function configureInstall(object $service, object $config): void
{
    $method = (new ReflectionClass($service))->getMethod('configureInstallation');
    $method->invoke($service, $config);
}

describe('InstallationService::configureInstallation()', function (): void {
    it('drops the rewrite rules so WordPress rebuilds them on the first request', function (): void {
        $deleted = [];
        $flushed = false;

        Brain\Monkey\Functions\when('update_option')->justReturn(true);
        Brain\Monkey\Functions\when('remove_all_filters')->justReturn(true);
        Brain\Monkey\Functions\when('wp_upload_dir')->justReturn(['error' => false]);
        Brain\Monkey\Functions\when('delete_option')->alias(function (string $option) use (&$deleted): bool {
            $deleted[] = $option;

            return true;
        });
        Brain\Monkey\Functions\when('flush_rewrite_rules')->alias(function () use (&$flushed): void {
            $flushed = true;
        });

        configureInstall($this->service, $this->config);

        expect($deleted)->toContain('rewrite_rules')
            // Flushing here would store the incomplete set, which is the bug.
            ->and($flushed)->toBeFalse();
    });

    it('enables pretty permalinks and reinitialises the rewrite component', function (): void {
        $options = [];

        Brain\Monkey\Functions\when('update_option')->alias(function (string $key, $value) use (&$options): bool {
            $options[$key] = $value;

            return true;
        });
        Brain\Monkey\Functions\when('remove_all_filters')->justReturn(true);
        Brain\Monkey\Functions\when('delete_option')->justReturn(true);
        Brain\Monkey\Functions\when('wp_upload_dir')->justReturn(['error' => false]);

        configureInstall($this->service, $this->config);

        expect($options['permalink_structure'] ?? null)->toBe('/%postname%/')
            ->and($GLOBALS['wp_rewrite']->initialised)->toBeTrue();
    });
});
