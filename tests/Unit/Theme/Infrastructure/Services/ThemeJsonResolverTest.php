<?php

declare(strict_types=1);

use Pollora\Theme\Infrastructure\Services\ThemeJsonResolver;

describe('ThemeJsonResolver', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/test_theme_json_'.uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->resolver = new ThemeJsonResolver($this->tempDir);
    });

    afterEach(function (): void {
        // Cleanup temp directory recursively
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

    it('returns null when build directory does not exist', function (): void {
        $result = $this->resolver->resolve('nonexistent-theme');

        expect($result)->toBeNull();
    });

    it('returns null when theme.json file does not exist', function (): void {
        $buildDir = $this->tempDir.'/build/theme/apiary/assets';
        mkdir($buildDir, 0777, true);

        $result = $this->resolver->resolve('apiary');

        expect($result)->toBeNull();
    });

    it('resolves valid theme.json data from build directory', function (): void {
        $buildDir = $this->tempDir.'/build/theme/apiary/assets';
        mkdir($buildDir, 0777, true);

        $themeData = [
            'version' => 2,
            'settings' => [
                'color' => [
                    'palette' => [
                        ['name' => 'Red', 'slug' => 'red', 'color' => '#ff0000'],
                        ['name' => 'Blue', 'slug' => 'blue', 'color' => '#0000ff'],
                    ],
                ],
                'typography' => [
                    'fontSizes' => [
                        ['name' => 'sm', 'slug' => 'sm', 'size' => '.875rem'],
                    ],
                ],
            ],
        ];
        file_put_contents($buildDir.'/theme.json', json_encode($themeData));

        $result = $this->resolver->resolve('apiary');

        expect($result)->toBeArray()
            ->and($result['version'])->toBe(2)
            ->and($result['settings']['color']['palette'])->toHaveCount(2)
            ->and($result['settings']['typography']['fontSizes'])->toHaveCount(1);
    });

    it('caches resolved data across multiple calls', function (): void {
        $buildDir = $this->tempDir.'/build/theme/cached-theme/assets';
        mkdir($buildDir, 0777, true);

        $themeData = ['version' => 2, 'settings' => []];
        file_put_contents($buildDir.'/theme.json', json_encode($themeData));

        $first = $this->resolver->resolve('cached-theme');
        // Remove the file to prove second call uses cache
        unlink($buildDir.'/theme.json');
        $second = $this->resolver->resolve('cached-theme');

        expect($first)->toBe($second)
            ->and($first)->toBeArray();
    });

    it('caches null result for missing files', function (): void {
        $first = $this->resolver->resolve('missing-theme');
        $second = $this->resolver->resolve('missing-theme');

        expect($first)->toBeNull()
            ->and($second)->toBeNull();
    });

    it('returns null for invalid JSON content', function (): void {
        $buildDir = $this->tempDir.'/build/theme/broken/assets';
        mkdir($buildDir, 0777, true);
        file_put_contents($buildDir.'/theme.json', '{invalid json!!!');

        $result = $this->resolver->resolve('broken');

        expect($result)->toBeNull();
    });

    it('resolves different themes independently', function (): void {
        // Setup theme A
        $buildDirA = $this->tempDir.'/build/theme/alpha/assets';
        mkdir($buildDirA, 0777, true);
        file_put_contents($buildDirA.'/theme.json', json_encode(['version' => 2, 'theme' => 'alpha']));

        // Setup theme B
        $buildDirB = $this->tempDir.'/build/theme/beta/assets';
        mkdir($buildDirB, 0777, true);
        file_put_contents($buildDirB.'/theme.json', json_encode(['version' => 3, 'theme' => 'beta']));

        $resultA = $this->resolver->resolve('alpha');
        $resultB = $this->resolver->resolve('beta');

        expect($resultA['theme'])->toBe('alpha')
            ->and($resultA['version'])->toBe(2)
            ->and($resultB['theme'])->toBe('beta')
            ->and($resultB['version'])->toBe(3);
    });

    it('handles public path with trailing slash', function (): void {
        $resolver = new ThemeJsonResolver($this->tempDir.'/');
        $buildDir = $this->tempDir.'/build/theme/slash-test/assets';
        mkdir($buildDir, 0777, true);
        file_put_contents($buildDir.'/theme.json', json_encode(['version' => 2]));

        $result = $resolver->resolve('slash-test');

        expect($result)->toBeArray()
            ->and($result['version'])->toBe(2);
    });
});