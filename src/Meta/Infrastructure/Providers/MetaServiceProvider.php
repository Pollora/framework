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
use Pollora\Meta\Application\Services\MetaUiDrivers;
use Pollora\Meta\Domain\Contracts\MetaRegistryInterface;
use Pollora\Meta\Domain\Contracts\MetaStoreInterface;
use Pollora\Meta\Domain\Contracts\MetaValidatorInterface;
use Pollora\Meta\Domain\Events\MetaSchemasRegistered;
use Pollora\Meta\Domain\Services\MetaValueCaster;
use Pollora\Meta\Infrastructure\Adapters\WordPressMetaRegistry;
use Pollora\Meta\Infrastructure\Adapters\WordPressMetaStore;
use Pollora\Meta\Infrastructure\Adapters\WordPressRestMetaValidation;
use Pollora\Meta\Infrastructure\Services\LaravelMetaValidator;
use Pollora\Meta\Infrastructure\Services\MetaDiscovery;
use Psr\Log\LoggerInterface;

/**
 * Typed meta: `#[Meta]` discovery, `register_meta()`, `Meta::of()`, the
 * `rules` of typed meta applied to REST writes, and the hand-off of the schemas
 * to a UI driver (`meta.ui`).
 *
 * Bindings:
 *  - `wp.meta` → {@see MetaAccessor} (singleton, used by the Meta facade)
 *  - {@see MetaDiscovery} (singleton, picked up by the discovery engine)
 */
class MetaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/meta.php', 'meta');
        $this->app->singleton(MetaUiDrivers::class, fn (Application $app): MetaUiDrivers => new MetaUiDrivers($app));
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
            $app->make(MetaUiDrivers::class),
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
        // Once every schema is registered with WordPress (priority 20).
        $this->app->make(Action::class)->add('init', $this->announceSchemas(...), WordPressMetaRegistry::INIT_PRIORITY + 1);

        $this->app->make(Filter::class)->add(
            'rest_request_before_callbacks',
            fn (mixed $response, array $handler, \WP_REST_Request $request): mixed => $this->app->make(WordPressRestMetaValidation::class)->validate($response, $handler, $request),
            10,
            3
        );
    }

    /**
     * Dispatches MetaSchemasRegistered and hands the schemas to the UI driver
     * the project picks in `meta.ui`.
     */
    private function announceSchemas(): void
    {
        $schemas = $this->app->make(MetaSchemaRepository::class)->all();
        $this->app->make('events')->dispatch(new MetaSchemasRegistered($schemas));
        $driver = $this->app->make('config')->get('meta.ui');

        if (is_string($driver) && $driver !== '') {
            $drivers = $this->app->make(MetaUiDrivers::class);
            $drivers->build($drivers->driver($driver), $schemas);
        }
    }
}
