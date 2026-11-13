<?php

declare(strict_types=1);

use Pollora\Plugin\Domain\Models\PluginMetadata;

beforeEach(function (): void {
    $this->base = sys_get_temp_dir().'/pollora-plugin-metadata-'.uniqid();
    mkdir($this->base.'/acme', 0755, true);
});

afterEach(function (): void {
    foreach (glob($this->base.'/acme/*') ?: [] as $file) {
        unlink($file);
    }

    rmdir($this->base.'/acme');
    rmdir($this->base);
});

it('loads the configuration from plugin.json', function (): void {
    file_put_contents($this->base.'/acme/plugin.json', '{"providers": ["Acme\\\\Provider"], "debug": true}');

    $metadata = new PluginMetadata('acme', $this->base);
    $metadata->loadConfiguration();

    expect($metadata->getConfig())->toBe(['providers' => ['Acme\\Provider'], 'debug' => true]);
});

it('keeps an empty configuration when plugin.json is absent', function (): void {
    $metadata = new PluginMetadata('acme', $this->base);
    $metadata->loadConfiguration();

    expect($metadata->getConfig())->toBe([]);
});

it('keeps an empty configuration when plugin.json is not valid JSON', function (): void {
    file_put_contents($this->base.'/acme/plugin.json', '{not json');

    $metadata = new PluginMetadata('acme', $this->base);
    $metadata->loadConfiguration();

    expect($metadata->getConfig())->toBe([]);
});

it('puts the includes directory under the plugin app directory', function (): void {
    $metadata = new PluginMetadata('acme', $this->base);

    expect($metadata->getPluginIncDir())->toBe($this->base.'/acme/app/inc');
});
