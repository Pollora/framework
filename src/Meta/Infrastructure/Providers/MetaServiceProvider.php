<?php

declare(strict_types=1);

namespace Pollora\Meta\Infrastructure\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Meta\Application\Services\MetaAccessor;
use Pollora\Meta\Application\Services\MetaSchemaBuilder;
use Pollora\Meta\Application\Services\MetaSchemaRepository;
use Pollora\Meta\Domain\Contracts\MetaRegistryInterface;
use Pollora\Meta\Domain\Contracts\MetaStoreInterface;
use Pollora\Meta\Domain\Contracts\MetaValidatorInterface;
use Pollora\Meta\Domain\Services\MetaValueCaster;
use Pollora\Meta\Infrastructure\Adapters\WordPressMetaRegistry;
use Pollora\Meta\Infrastructure\Adapters\WordPressMetaStore;
use Pollora\Meta\Infrastructure\Adapters\WordPressRestMetaValidation;
use Pollora\Meta\Infrastructure\Services\LaravelMetaValidator;
use Pollora\Meta\Infrastructure\Services\MetaDiscovery;
use Psr\Log\LoggerInterface;

/**
 * Typed meta: `#[Meta]` discovery, `register_meta()`, `Meta::of()`, and the
 * `rules` of typed meta applied to REST writes.
 *
 * Bindings:
 *  - `wp.meta` → {@see MetaAccessor} (singleton, used by the Meta facade)
 *  - {@see MetaDiscovery} (singleton, picked up by the discovery engine)
 */
class MetaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MetaValueCaster::class);
        $this->app->singleton(MetaSchemaBuilder::class);
        $this->app->singleton(MetaSchemaRepository::class);
        $this->app->singleton(MetaStoreInterface::class, WordPressMetaStore::class);
        $this->app->singleton(MetaValidatorInterface::class, fn (Application $app): LaravelMetaValidator => new LaravelMetaValidator($app->make('validator')));

        $this->app->singleton(MetaRegistryInterface::class, fn (Application $app): WordPressMetaRegistry => new WordPressMetaRegistry(
            $app->make(Action::class),
            $app->make(MetaValueCaster::class),
        ));

        $this->app->singleton('wp.meta', fn (Application $app): MetaAccessor => new MetaAccessor(
            $app->make(MetaSchemaRepository::class),
            $app->make(MetaSchemaBuilder::class),
            $app->make(MetaStoreInterface::class),
            $app->make(MetaValueCaster::class),
            (bool) $app->make('config')->get('app.debug', false),
            $app->make(LoggerInterface::class),
            $app->make(MetaValidatorInterface::class),
        ));
        $this->app->alias('wp.meta', MetaAccessor::class);

        $this->app->singleton(MetaDiscovery::class, fn (Application $app): MetaDiscovery => new MetaDiscovery(
            $app->make(MetaSchemaBuilder::class),
            $app->make(MetaSchemaRepository::class),
            $app->make(MetaRegistryInterface::class),
            $app->make(LoggerInterface::class),
        ));
    }

    public function boot(): void
    {
        $this->app->make(Filter::class)->add(
            'rest_request_before_callbacks',
            fn (mixed $response, array $handler, \WP_REST_Request $request): mixed => $this->app->make(WordPressRestMetaValidation::class)->validate($response, $handler, $request),
            10,
            3
        );
    }
}
