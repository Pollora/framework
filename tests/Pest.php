<?php

declare(strict_types=1);

use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Application\Services\AssetRetrievalService;
use Pollora\Asset\Domain\Services\AssetContainerManager;
use Pollora\Asset\Infrastructure\Repositories\InMemoryAssetRepository;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Tests\Support\FakeDiscoveryEngine;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)->in('Feature/Modules', 'Feature/Option');
uses(TestCase::class)->in('Unit');
uses(Orchestra\Testbench\TestCase::class)
    ->beforeEach(function (): void {
        \Brain\Monkey\setUp();
        setupDefaultWordPressStubs();
    })
    ->afterEach(function (): void {
        \Brain\Monkey\tearDown();
    })
    ->in('Feature/Route', 'Feature/Theme', 'Feature/Discovery', 'Feature/Hook', 'Feature/VersionCheck', 'Feature/Dashboard', 'Feature/Doctor', 'Feature/Schedule', 'Feature/Block', 'Feature/Plugin', 'Feature/PostType', 'Feature/Asset', 'Feature/WordPress', 'Feature/ModuleScaffold', 'Feature/ModuleActivation');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something(): void
{
    // ..
}

/**
 * A real asset manager over an empty in-memory repository, with no containers.
 *
 * The asset manager is final in the pollora/assets package, so tests build one
 * instead of mocking it: they register containers with addContainer() and read
 * them back with getContainer().
 */
function realAssetManager(): AssetManager
{
    return new AssetManager(new AssetRetrievalService(new AssetContainerManager(new InMemoryAssetRepository)));
}

/**
 * A real discovery manager over the given engine.
 *
 * The discovery manager is final in the pollora/discovery package, so tests
 * build one around an engine (an interface) instead of mocking the manager.
 * With no engine, a fake one that holds no discovery and no location is used.
 *
 * @param  DiscoveryEngineInterface|null  $engine  The engine the manager delegates to
 */
function realDiscoveryManager(?DiscoveryEngineInterface $engine = null): DiscoveryManager
{
    return new DiscoveryManager($engine ?? new FakeDiscoveryEngine);
}
