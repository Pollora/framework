<?php

declare(strict_types=1);

use Pollora\Plugin\Domain\Models\PluginMetadata;

describe('PluginMetadata', function (): void {
    beforeEach(function (): void {
        $this->pluginName = 'test-plugin';
        $this->basePath = '/path/to/plugins';
        $this->metadata = new PluginMetadata($this->pluginName, $this->basePath);
    });

    it('can be instantiated with name and base path', function (): void {
        expect($this->metadata)->toBeInstanceOf(PluginMetadata::class);
        expect($this->metadata->getName())->toBe($this->pluginName);
    });

    it('returns correct base path', function (): void {
        expect($this->metadata->getBasePath())->toBe($this->basePath.'/'.$this->pluginName);
    });

    it('returns correct main file path', function (): void {
        expect($this->metadata->getMainFilePath())->toBe($this->basePath.'/'.$this->pluginName.'/'.$this->pluginName.'.php');
    });

    it('returns correct config path', function (): void {
        expect($this->metadata->getConfigPath())->toBe($this->basePath.'/'.$this->pluginName.'/plugin.json');
    });

    it('returns correct language path', function (): void {
        expect($this->metadata->getLanguagePath())->toBe($this->basePath.'/'.$this->pluginName.'/languages');
    });

    it('returns correct plugin namespace', function (): void {
        expect($this->metadata->getPluginNamespace())->toBe('TestPlugin');
    });

    it('handles complex plugin names', function (): void {
        $plugin = new PluginMetadata('my-awesome-plugin', $this->basePath);
        expect($plugin->getPluginNamespace())->toBe('MyAwesomePlugin');
    });

    it('returns correct plugin app dir', function (): void {
        expect($this->metadata->getPluginAppDir())->toBe($this->basePath.'/'.$this->pluginName.'/app');
    });

    it('returns correct plugin app dir with subdirectory', function (): void {
        expect($this->metadata->getPluginAppDir('Providers'))->toBe($this->basePath.'/'.$this->pluginName.'/app/Providers');
    });

    it('returns correct plugin app file', function (): void {
        expect($this->metadata->getPluginAppFile('ServiceProvider.php'))->toBe($this->basePath.'/'.$this->pluginName.'/app/ServiceProvider.php');
    });

    it('returns empty config initially', function (): void {
        expect($this->metadata->getConfig())->toBe([]);
    });

    it('returns correct slug', function (): void {
        expect($this->metadata->getSlug())->toBe('test-plugin');
    });

    it('returns correct basename', function (): void {
        expect($this->metadata->getBasename())->toBe($this->pluginName.'/'.$this->pluginName.'.php');
    });

    it('handles plugin name with spaces', function (): void {
        $plugin = new PluginMetadata('My Plugin Name', $this->basePath);
        expect($plugin->getSlug())->toBe('my-plugin-name');
    });

    it('handles plugin name with underscores', function (): void {
        $plugin = new PluginMetadata('my_plugin_name', $this->basePath);
        expect($plugin->getSlug())->toBe('my-plugin-name');
    });

    it('returns correct views path', function (): void {
        expect($this->metadata->getViewsPath())->toBe($this->basePath.'/'.$this->pluginName.'/views');
    });

    it('returns correct assets path', function (): void {
        expect($this->metadata->getAssetsPath())->toBe($this->basePath.'/'.$this->pluginName.'/assets');
    });

    it('returns correct routes path', function (): void {
        expect($this->metadata->getRoutesPath())->toBe($this->basePath.'/'.$this->pluginName.'/routes');
    });

    it('returns correct config dir', function (): void {
        expect($this->metadata->getConfigDir())->toBe($this->basePath.'/'.$this->pluginName.'/config');
    });

    it('returns correct database path', function (): void {
        expect($this->metadata->getDatabasePath())->toBe($this->basePath.'/'.$this->pluginName.'/database');
    });

    it('returns correct tests path', function (): void {
        expect($this->metadata->getTestsPath())->toBe($this->basePath.'/'.$this->pluginName.'/tests');
    });

    it('returns path for item with array', function (): void {
        expect($this->metadata->getPathForItem(['config', 'app.php']))->toBe($this->basePath.'/'.$this->pluginName.'/config/app.php');
    });

    it('returns path for item with string', function (): void {
        expect($this->metadata->getPathForItem('config'))->toBe($this->basePath.'/'.$this->pluginName.'/config');
    });

    it('returns path for item with null', function (): void {
        expect($this->metadata->getPathForItem(null))->toBe($this->basePath.'/'.$this->pluginName.'/');
    });
});
