<?php

declare(strict_types=1);

use Pollora\Plugin\Application\Services\PluginManager;
use Pollora\Plugin\Application\Services\PluginRegistrar;
use Pollora\Plugin\Infrastructure\Providers\PluginServiceProvider;
use Pollora\Plugin\Infrastructure\Repositories\PluginRepository;
use Pollora\Plugin\Infrastructure\Services\PluginAutoloader;
use Pollora\Plugin\Infrastructure\Services\WordPressPluginParser;

beforeEach(function (): void {
    // Only test register(), not boot() - boot has filesystem dependencies
    $this->provider = new PluginServiceProvider($this->app);
    $this->provider->register();
});

describe('PluginServiceProvider', function (): void {
    it('binds PluginAutoloader as singleton', function (): void {
        $autoloader = $this->app->make(PluginAutoloader::class);

        expect($autoloader)->toBeInstanceOf(PluginAutoloader::class);
        expect($this->app->make(PluginAutoloader::class))->toBe($autoloader);
    });

    it('binds WordPressPluginParser as singleton', function (): void {
        $parser = $this->app->make(WordPressPluginParser::class);

        expect($parser)->toBeInstanceOf(WordPressPluginParser::class);
        expect($this->app->make(WordPressPluginParser::class))->toBe($parser);
    });

    it('binds PluginRepository as singleton', function (): void {
        $repo = $this->app->make(PluginRepository::class);

        expect($repo)->toBeInstanceOf(PluginRepository::class);
        expect($this->app->make(PluginRepository::class))->toBe($repo);
    });

    it('provides plugin.repository alias', function (): void {
        expect($this->app->bound('plugin.repository'))->toBeTrue();
    });

    it('registers PluginManager binding', function (): void {
        expect($this->app->bound(PluginManager::class))->toBeTrue();
    });

    it('provides plugin.manager alias', function (): void {
        expect($this->app->isAlias('plugin.manager'))->toBeTrue();
    });

    it('registers PluginRegistrar binding', function (): void {
        expect($this->app->bound(PluginRegistrar::class))->toBeTrue();
    });

    it('provides plugin.registrar alias', function (): void {
        expect($this->app->isAlias('plugin.registrar'))->toBeTrue();
    });

    it('declares provided services', function (): void {
        $provides = $this->provider->provides();

        expect($provides)->toContain(PluginAutoloader::class);
        expect($provides)->toContain(WordPressPluginParser::class);
        expect($provides)->toContain(PluginRepository::class);
        expect($provides)->toContain(PluginManager::class);
        expect($provides)->toContain(PluginRegistrar::class);
        expect($provides)->toContain('plugin.repository');
        expect($provides)->toContain('plugin.manager');
        expect($provides)->toContain('plugin.registrar');
    });
});
