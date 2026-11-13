<?php

declare(strict_types=1);

use Pollora\Theme\Domain\Models\ThemeMetadata;

describe('ThemeMetadata', function (): void {
    it('derives every path from the base path and the theme name', function (): void {
        $meta = new ThemeMetadata('my-theme', '/themes');

        expect($meta->getName())->toBe('my-theme')
            ->and($meta->getBasePath())->toBe('/themes/my-theme')
            ->and($meta->getViewPath())->toBe('/themes/my-theme/resources/views')
            ->and($meta->getConfigPath())->toBe('/themes/my-theme/theme.json')
            ->and($meta->getLanguagePath())->toBe('/themes/my-theme/lang')
            ->and($meta->getThemeIncDir())->toStartWith('/themes/my-theme')->toEndWith('inc');
    });

    it('builds namespaces and camelCase names from kebab and snake case', function (): void {
        expect((new ThemeMetadata('my-cool_theme', '/t'))->getThemeNamespace())->toBe('Theme\\MyCoolTheme')
            ->and((new ThemeMetadata('my-cool_theme', '/t'))->getThemeCamelCase())->toBe('myCoolTheme');
    });

    it('resolves item paths from a string, an array or nothing', function (): void {
        $meta = new ThemeMetadata('t', '/b');

        expect($meta->getPathForItem('assets'))->toBe('/b/t/assets')
            ->and($meta->getPathForItem(['assets', 'img']))->toBe('/b/t/assets/img')
            ->and($meta->getPathForItem())->toBe('/b/t/');
    });

    it('builds app file paths under the theme', function (): void {
        $meta = new ThemeMetadata('t', '/b');

        expect($meta->getThemeAppFile('boot.php'))->toStartWith('/b/t')->toEndWith('boot.php');
    });

    it('builds the app directory with a single separator', function (): void {
        $meta = new ThemeMetadata('t', '/b');

        expect($meta->getThemeAppDir())->toBe('/b/t')
            ->and($meta->getThemeAppDir('inc'))->toBe('/b/t/inc')
            ->and($meta->getThemeAppFile('boot.php'))->toBe('/b/t/boot.php');
    });

    it('has no parent and no config before the configuration is loaded', function (): void {
        $meta = new ThemeMetadata('t', '/nope');
        $meta->loadConfiguration();

        expect($meta->getParentTheme())->toBeNull()
            ->and($meta->getConfig())->toBe([]);
    });

    it('reads the parent theme and the config from theme.json', function (): void {
        $base = sys_get_temp_dir().'/pollora-meta-'.bin2hex(random_bytes(6));
        mkdir($base.'/child', 0777, true);
        file_put_contents($base.'/child/theme.json', json_encode(['parent' => 'base', 'x' => 1]));

        try {
            $meta = new ThemeMetadata('child', $base);
            $meta->loadConfiguration();
            $result = [$meta->getParentTheme(), $meta->getConfig()];
        } finally {
            @unlink($base.'/child/theme.json');
            @rmdir($base.'/child');
            @rmdir($base);
        }

        expect($result)->toBe(['base', ['parent' => 'base', 'x' => 1]]);
    });

    it('keeps the config but no parent when theme.json declares none', function (): void {
        $base = sys_get_temp_dir().'/pollora-meta-'.bin2hex(random_bytes(6));
        mkdir($base.'/solo', 0777, true);
        file_put_contents($base.'/solo/theme.json', json_encode(['version' => 3]));

        try {
            $meta = new ThemeMetadata('solo', $base);
            $meta->loadConfiguration();
            $result = [$meta->getParentTheme(), $meta->getConfig()];
        } finally {
            @unlink($base.'/solo/theme.json');
            @rmdir($base.'/solo');
            @rmdir($base);
        }

        expect($result)->toBe([null, ['version' => 3]]);
    });
});
