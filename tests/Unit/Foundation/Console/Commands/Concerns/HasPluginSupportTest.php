<?php

declare(strict_types=1);

use Pollora\Foundation\Console\Commands\Concerns\HasPluginSupport;

if (! defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', '/var/www/html/wp-content/plugins');
}

describe('HasPluginSupport', function (): void {
    beforeEach(function (): void {
        $this->command = new class
        {
            use HasPluginSupport {
                getPluginOptions as public;
                getPluginOption as public;
                hasPluginOption as public;
                resolvePlugin as public;
                getPluginPath as public;
                getPluginNamespace as public;
                getPluginSourcePath as public;
                getPluginSourceNamespace as public;
                resolvePluginLocation as public;
            }

            private ?string $pluginOption = null;

            public function setPluginOption(?string $value): void
            {
                $this->pluginOption = $value;
            }

            public function option($key = null)
            {
                return $this->pluginOption;
            }
        };
    });

    it('returns plugin option definition', function (): void {
        $options = $this->command->getPluginOptions();

        expect($options[0][0])->toBe('plugin');
    });

    it('returns null when no plugin set', function (): void {
        expect($this->command->getPluginOption())->toBeNull();
    });

    it('reports no plugin option when null', function (): void {
        expect($this->command->hasPluginOption())->toBeFalse();
    });

    it('reports plugin option when set', function (): void {
        $this->command->setPluginOption('my-plugin');

        expect($this->command->hasPluginOption())->toBeTrue();
    });

    it('generates plugin path with WP_PLUGIN_DIR', function (): void {
        $this->command->setPluginOption('my-plugin');

        expect($this->command->getPluginPath())->toBe(WP_PLUGIN_DIR.DIRECTORY_SEPARATOR.'my-plugin');
    });

    it('returns empty path when no plugin', function (): void {
        expect($this->command->getPluginPath())->toBe('');
    });

    it('generates studly plugin namespace', function (): void {
        $this->command->setPluginOption('my-plugin');

        expect($this->command->getPluginNamespace())->toBe('Plugin\\MyPlugin');
    });

    it('appends /app to source path', function (): void {
        $this->command->setPluginOption('my-plugin');

        expect($this->command->getPluginSourcePath())->toEndWith('my-plugin/app');
    });

    it('appends backslash to source namespace', function (): void {
        $this->command->setPluginOption('my-plugin');

        expect($this->command->getPluginSourceNamespace())->toBe('Plugin\\MyPlugin\\');
    });

    it('resolves full plugin location', function (): void {
        $this->command->setPluginOption('my-plugin');

        $location = $this->command->resolvePluginLocation();

        expect($location['type'])->toBe('plugin');
        expect($location['name'])->toBe('my-plugin');
        expect($location['namespace'])->toBe('Plugin\\MyPlugin');
    });

    it('throws when resolving empty plugin name', function (): void {
        $this->command->setPluginOption('');

        expect(fn () => $this->command->resolvePluginLocation())
            ->toThrow(InvalidArgumentException::class, 'Plugin name cannot be empty');
    });
});
