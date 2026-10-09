<?php

declare(strict_types=1);

use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Application\Services\AssetRetrievalService;
use Pollora\Asset\Infrastructure\Repositories\AssetContainer;

it('lists every container, keyed by name', function (): void {
    $manager = new AssetManager(Mockery::mock(AssetRetrievalService::class));
    $manager->addContainer('theme', ['hot_file' => 'theme.hot']);
    $manager->addContainer('acme-plugin', []);

    $containers = $manager->containers();

    expect(array_keys($containers))->toBe(['theme', 'acme-plugin'])
        ->and($containers['theme'])->toBeInstanceOf(AssetContainer::class)
        ->and($containers['theme']->getName())->toBe('theme');
});

it('lists nothing before a container is added', function (): void {
    expect((new AssetManager(Mockery::mock(AssetRetrievalService::class)))->containers())->toBe([]);
});
