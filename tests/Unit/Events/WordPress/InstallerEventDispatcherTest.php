<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Events\WordPress\Installer\Plugin\PluginActivated;
use Pollora\Events\WordPress\Installer\Plugin\PluginDeactivated;
use Pollora\Events\WordPress\Installer\Plugin\PluginInstalled;
use Pollora\Events\WordPress\Installer\Plugin\PluginUpdated;
use Pollora\Events\WordPress\Installer\Theme\ThemeActivated;
use Pollora\Events\WordPress\Installer\Theme\ThemeDeleted;
use Pollora\Events\WordPress\Installer\Theme\ThemeInstalled;
use Pollora\Events\WordPress\Installer\Theme\ThemeUpdated;
use Pollora\Events\WordPress\Installer\WordPressUpdated;
use Tests\Unit\Events\Fixtures\FakeTheme;
use Tests\Unit\Events\Fixtures\FakeUpgrader;
use Tests\Unit\Events\Fixtures\TestableInstallerDispatcher;

use function Tests\Unit\Events\Fixtures\installer_wordpress;
use function Tests\Unit\Events\Fixtures\make_dispatcher;

require_once __DIR__.'/fixtures.php';
require_once __DIR__.'/installer-functions.php';

if (! defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', sys_get_temp_dir().'/wp-content/plugins');
}

/*
 * delete_theme() is declared globally because the dispatcher recognises a theme
 * deletion by finding this function in the call stack. Nothing else uses it.
 */
if (! function_exists('delete_theme')) {
    function delete_theme(string $stylesheet): void
    {
        ($GLOBALS['pollora_delete_theme_callback'])();
    }
}

describe('InstallerEventDispatcher plugins', function (): void {
    beforeEach(function (): void {
        [$this->dispatcher, $this->log] = make_dispatcher(TestableInstallerDispatcher::class);
        $this->dispatcher->plugins = [
            'akismet/akismet.php' => ['Name' => 'Akismet', 'Version' => '5.0'],
            'hello/hello.php' => ['Name' => 'Hello Dolly', 'Version' => '1.7'],
        ];
    });

    it('dispatches PluginInstalled with the name and version read from the plugin header', function (): void {
        installer_wordpress(['get_plugin_data' => fn (string $file): array => ['Name' => 'Akismet', 'Version' => '5.1']]);

        $upgrader = new FakeUpgrader(['result' => ['destination_name' => 'akismet', 'local_destination' => '/wp/plugins']]);
        $upgrader->result = ['destination_name' => 'akismet'];

        $this->dispatcher->handleUpgraderProcessComplete($upgrader, ['type' => 'plugin', 'action' => 'install']);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(PluginInstalled::class)
            ->and($this->log[0]->name)->toBe('Akismet')
            ->and($this->log[0]->version)->toBe('5.1')
            ->and($this->log[0]->slug)->toBe('akismet');
    });

    it('ignores an installation that left no destination', function (string $type): void {
        $upgrader = new FakeUpgrader(['result' => []]);

        $this->dispatcher->handleUpgraderProcessComplete($upgrader, ['type' => $type, 'action' => 'install']);

        expect($this->log)->toHaveCount(0);
    })->with(['plugin', 'theme']);

    it('ignores upgrades that are neither plugins nor themes', function (array $extra): void {
        $this->dispatcher->handleUpgraderProcessComplete(new FakeUpgrader, $extra);

        expect($this->log)->toHaveCount(0);
    })->with([
        'core' => [['type' => 'core', 'action' => 'update']],
        'no type' => [['action' => 'update']],
    ]);

    it('ignores actions other than install and update', function (string $type): void {
        $this->dispatcher->handleUpgraderProcessComplete(new FakeUpgrader, ['type' => $type, 'action' => 'delete']);

        expect($this->log)->toHaveCount(0);
    })->with(['plugin', 'theme']);

    it('dispatches PluginUpdated with the new and the previous version', function (): void {
        installer_wordpress(['get_plugin_data' => fn (string $file): array => $file === WP_PLUGIN_DIR.'/akismet/akismet.php'
            ? ['Name' => 'Akismet', 'Version' => '5.2']
            : throw new LogicException($file)]);

        $this->dispatcher->handleUpgraderProcessComplete(
            new FakeUpgrader(['plugin' => 'akismet/akismet.php']),
            ['type' => 'plugin', 'action' => 'update'],
        );

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(PluginUpdated::class)
            ->and($this->log[0]->name)->toBe('Akismet')
            ->and($this->log[0]->version)->toBe('5.2')
            ->and($this->log[0]->oldVersion)->toBe('5.0')
            ->and($this->log[0]->slug)->toBe('akismet/akismet.php');
    });

    it('dispatches one PluginUpdated per plugin of a bulk update', function (): void {
        installer_wordpress(['get_plugin_data' => fn (string $file): array => str_contains($file, 'akismet')
            ? ['Name' => 'Akismet', 'Version' => '5.2']
            : ['Name' => 'Hello Dolly', 'Version' => '1.8']]);

        $this->dispatcher->handleUpgraderProcessComplete(
            new FakeUpgrader,
            ['type' => 'plugin', 'action' => 'update', 'bulk' => true, 'plugins' => ['akismet/akismet.php', 'hello/hello.php']],
        );

        expect($this->log)->toHaveCount(2)
            ->and($this->log[0]->slug)->toBe('akismet/akismet.php')
            ->and($this->log[0]->oldVersion)->toBe('5.0')
            ->and($this->log[0]->version)->toBe('5.2')
            ->and($this->log[1]->slug)->toBe('hello/hello.php')
            ->and($this->log[1]->name)->toBe('Hello Dolly')
            ->and($this->log[1]->oldVersion)->toBe('1.7')
            ->and($this->log[1]->version)->toBe('1.8');
    });

    it('dispatches PluginActivated and PluginDeactivated with the name of the plugin', function (string $handler, string $expected, bool $networkWide): void {
        $this->dispatcher->{$handler}('hello/hello.php', $networkWide);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf($expected)
            ->and($this->log[0]->name)->toBe('Hello Dolly')
            ->and($this->log[0]->slug)->toBe('hello/hello.php')
            ->and($this->log[0]->networkWide)->toBe($networkWide);
    })->with([
        'activated on one site' => ['handleActivatePlugin', PluginActivated::class, false],
        'activated network wide' => ['handleActivatePlugin', PluginActivated::class, true],
        'deactivated on one site' => ['handleDeactivatePlugin', PluginDeactivated::class, false],
        'deactivated network wide' => ['handleDeactivatePlugin', PluginDeactivated::class, true],
    ]);

    it('does not remember plugins to delete outside a deletion request', function (): void {
        Functions\expect('update_option')->never();

        expect($this->dispatcher->handlePreOptionUninstallPlugins())->toBeFalse()
            ->and($this->log)->toHaveCount(0);
    });

    it('lets the transient through untouched when no deletion was confirmed', function (): void {
        Functions\when('get_option')->justReturn(['hello/hello.php' => ['Name' => 'Hello Dolly']]);
        Functions\expect('delete_option')->never();

        $transient = (object) ['checked' => ['a' => '1']];

        expect($this->dispatcher->handlePreSetSiteTransientUpdatePlugins($transient))->toBe($transient)
            ->and($this->log)->toHaveCount(0);
    });

    it('lets the transient through when there is nothing to delete', function (): void {
        Functions\when('get_option')->justReturn(false);

        expect($this->dispatcher->handlePreSetSiteTransientUpdatePlugins('value'))->toBe('value')
            ->and($this->log)->toHaveCount(0);
    });
});

describe('InstallerEventDispatcher themes', function (): void {
    beforeEach(function (): void {
        [$this->dispatcher, $this->log] = make_dispatcher(TestableInstallerDispatcher::class);
    });

    afterEach(function (): void {
        unset($GLOBALS['pollora_delete_theme_callback']);
    });

    it('dispatches ThemeInstalled with the name and version of the installed theme', function (): void {
        $cleaned = 0;
        installer_wordpress([
            'wp_clean_themes_cache' => function () use (&$cleaned): void {
                $cleaned++;
            },
            'wp_get_theme' => fn (string $slug): FakeTheme => $slug === 'nectar'
                ? new FakeTheme(['Name' => 'Nectar', 'Version' => '1.7'])
                : throw new LogicException($slug),
        ]);

        $upgrader = new FakeUpgrader(['result' => ['destination_name' => 'nectar']]);

        $this->dispatcher->handleUpgraderProcessComplete($upgrader, ['type' => 'theme', 'action' => 'install']);

        expect($cleaned)->toBe(1)
            ->and($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(ThemeInstalled::class)
            ->and($this->log[0]->name)->toBe('Nectar')
            ->and($this->log[0]->version)->toBe('1.7')
            ->and($this->log[0]->slug)->toBe('nectar');
    });

    it('dispatches ThemeUpdated with the version found in the stylesheet and the previous one', function (): void {
        installer_wordpress([
            'wp_get_theme' => fn (string $slug): FakeTheme => new FakeTheme(['Name' => 'Nectar', 'Version' => '1.6', 'Stylesheet Dir' => '/themes/'.$slug]),
            'get_file_data' => fn (string $file, array $headers): array => $file === '/themes/nectar/style.css' && $headers === ['Version' => 'Version']
                ? ['Version' => '1.7']
                : throw new LogicException($file),
        ]);

        $this->dispatcher->handleUpgraderProcessComplete(new FakeUpgrader(['theme' => 'nectar']), ['type' => 'theme', 'action' => 'update']);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(ThemeUpdated::class)
            ->and($this->log[0]->name)->toBe('Nectar')
            ->and($this->log[0]->version)->toBe('1.7')
            ->and($this->log[0]->oldVersion)->toBe('1.6')
            ->and($this->log[0]->slug)->toBe('nectar');
    });

    it('dispatches one ThemeUpdated per theme of a bulk update', function (): void {
        installer_wordpress([
            'wp_get_theme' => fn (string $slug): FakeTheme => new FakeTheme(['Name' => ucfirst($slug), 'Version' => '1.0', 'Stylesheet Dir' => '/themes/'.$slug]),
            'get_file_data' => fn (string $file): array => ['Version' => '2.0'],
        ]);

        $this->dispatcher->handleUpgraderProcessComplete(
            new FakeUpgrader,
            ['type' => 'theme', 'action' => 'update', 'bulk' => true, 'themes' => ['nectar', 'buzz']],
        );

        expect($this->log)->toHaveCount(2)
            ->and($this->log[0]->slug)->toBe('nectar')
            ->and($this->log[0]->name)->toBe('Nectar')
            ->and($this->log[1]->slug)->toBe('buzz')
            ->and($this->log[1]->name)->toBe('Buzz');
    });

    it('dispatches ThemeActivated with the name of the new theme', function (): void {
        $this->dispatcher->handleSwitchTheme('Nectar', 'nectar');

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(ThemeActivated::class)
            ->and($this->log[0]->name)->toBe('Nectar');
    });

    it('dispatches ThemeDeleted with the theme passed to delete_theme()', function (): void {
        $GLOBALS['pollora_delete_theme_callback'] = fn () => $this->dispatcher->handleDeleteSiteTransientUpdateThemes();

        delete_theme('old-theme');

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(ThemeDeleted::class)
            ->and($this->log[0]->name)->toBe('old-theme');
    });

    it('ignores a theme update transient that is not part of a theme deletion', function (): void {
        $this->dispatcher->handleDeleteSiteTransientUpdateThemes();

        expect($this->log)->toHaveCount(0);
    });
});

describe('InstallerEventDispatcher core', function (): void {
    beforeEach(function (): void {
        [$this->dispatcher, $this->log] = make_dispatcher(TestableInstallerDispatcher::class);
        $this->previous = [$GLOBALS['wp_version'] ?? null, $GLOBALS['pagenow'] ?? null];
        $GLOBALS['wp_version'] = '6.8';
    });

    afterEach(function (): void {
        [$GLOBALS['wp_version'], $GLOBALS['pagenow']] = $this->previous;
    });

    it('reports a core update started from the update screen as manual', function (): void {
        $GLOBALS['pagenow'] = 'update-core.php';

        $this->dispatcher->handleCoreUpdatedSuccessfully('6.9');

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(WordPressUpdated::class)
            ->and($this->log[0]->newVersion)->toBe('6.9')
            ->and($this->log[0]->oldVersion)->toBe('6.8')
            ->and($this->log[0]->autoUpdated)->toBeFalse();
    });

    it('reports a core update from anywhere else as automatic', function (): void {
        $GLOBALS['pagenow'] = 'wp-cron.php';

        $this->dispatcher->handleCoreUpdatedSuccessfully('6.9');

        expect($this->log[0]->autoUpdated)->toBeTrue();
    });
});
