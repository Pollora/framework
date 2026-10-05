<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Meta\Application\Services\MetaAccessor;
use Pollora\Meta\Domain\Contracts\MetaRegistryInterface;
use Pollora\Meta\Domain\Contracts\MetaStoreInterface;
use Pollora\Meta\Domain\Models\MetaRecord;
use Pollora\Meta\Infrastructure\Adapters\WordPressMetaRegistry;
use Pollora\Meta\Infrastructure\Adapters\WordPressMetaStore;
use Pollora\Meta\Infrastructure\Providers\MetaServiceProvider;
use Pollora\Meta\Infrastructure\Services\MetaDiscovery;
use Pollora\Support\Facades\Meta;
use Psr\Log\LoggerInterface;
use Tests\Unit\Meta\Fixtures\Event;

beforeEach(function (): void {
    $this->app = new Application(sys_get_temp_dir());
    $this->app->instance('config', new Repository(['app' => ['debug' => true]]));
    $this->app->instance(Action::class, Mockery::mock(Action::class));
    $this->app->instance(LoggerInterface::class, Mockery::mock(LoggerInterface::class));

    (new MetaServiceProvider($this->app))->register();
});

afterEach(function (): void {
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
});

it('binds the WordPress adapters and the discovery', function (): void {
    expect($this->app->make(MetaStoreInterface::class))->toBeInstanceOf(WordPressMetaStore::class)
        ->and($this->app->make(MetaRegistryInterface::class))->toBeInstanceOf(WordPressMetaRegistry::class)
        ->and($this->app->make(MetaDiscovery::class))->toBeInstanceOf(MetaDiscovery::class)
        ->and($this->app->make('wp.meta'))->toBe($this->app->make(MetaAccessor::class));
});

it('serves Meta::of() through the facade', function (): void {
    Facade::setFacadeApplication($this->app);

    expect(Meta::of(Event::class, 42))->toBeInstanceOf(MetaRecord::class);
});
