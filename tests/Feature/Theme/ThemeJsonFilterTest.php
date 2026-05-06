<?php

declare(strict_types=1);

use Pollora\Theme\Domain\Contracts\ThemeJsonResolverInterface;
use Pollora\Theme\Infrastructure\Providers\ThemeServiceProvider;
use Pollora\Theme\Infrastructure\Services\ThemeJsonResolver;

beforeEach(function (): void {
    Brain\Monkey\Functions\when('get_stylesheet')->justReturn('apiary');

    $this->tempDir = sys_get_temp_dir().'/test_theme_json_filter_'.uniqid();
    mkdir($this->tempDir, 0777, true);
});

afterEach(function (): void {
    $cleanup = function (string $dir) use (&$cleanup): void {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            is_dir($path) ? $cleanup($path) : unlink($path);
        }
        rmdir($dir);
    };
    $cleanup($this->tempDir);
});

describe('ThemeJsonFilter integration', function (): void {
    it('registers ThemeJsonResolverInterface as singleton', function (): void {
        $this->app->singleton('path.public', fn () => $this->tempDir);
        $this->app->bind(ThemeJsonResolverInterface::class, fn () => new ThemeJsonResolver($this->tempDir));

        $resolver = $this->app->make(ThemeJsonResolverInterface::class);

        expect($resolver)->toBeInstanceOf(ThemeJsonResolver::class);
    });

    it('resolves built theme.json when file exists', function (): void {
        $buildDir = $this->tempDir.'/build/theme/apiary/assets';
        mkdir($buildDir, 0777, true);
        file_put_contents($buildDir.'/theme.json', json_encode([
            'version' => 2,
            'settings' => [
                'color' => [
                    'palette' => [
                        ['name' => 'Primary', 'slug' => 'primary', 'color' => '#3490dc'],
                    ],
                ],
            ],
        ]));

        $resolver = new ThemeJsonResolver($this->tempDir);
        $result = $resolver->resolve('apiary');

        expect($result)->toBeArray()
            ->and($result['version'])->toBe(2)
            ->and($result['settings']['color']['palette'])->toHaveCount(1)
            ->and($result['settings']['color']['palette'][0]['slug'])->toBe('primary');
    });

    it('returns null and preserves original data when no built file exists', function (): void {
        $resolver = new ThemeJsonResolver($this->tempDir);
        $result = $resolver->resolve('apiary');

        expect($result)->toBeNull();
    });

    it('resolves theme.json for the active stylesheet slug', function (): void {
        Brain\Monkey\Functions\when('get_stylesheet')->justReturn('custom-theme');

        $buildDir = $this->tempDir.'/build/theme/custom-theme/assets';
        mkdir($buildDir, 0777, true);
        file_put_contents($buildDir.'/theme.json', json_encode([
            'version' => 3,
            'settings' => ['typography' => ['fontSizes' => []]],
        ]));

        $resolver = new ThemeJsonResolver($this->tempDir);
        $result = $resolver->resolve(get_stylesheet());

        expect($result)->toBeArray()
            ->and($result['version'])->toBe(3);
    });
});