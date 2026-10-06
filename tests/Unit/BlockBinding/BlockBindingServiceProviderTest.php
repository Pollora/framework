<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Pollora\BlockBinding\Application\Services\BindingResolver;
use Pollora\BlockBinding\Application\Services\BindingSourceRegistry;
use Pollora\BlockBinding\Infrastructure\Providers\BlockBindingServiceProvider;
use Pollora\BlockBinding\Infrastructure\Services\BlockBindingDiscovery;
use Pollora\BlockBinding\UI\Console\MakeBindingCommand;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Meta\Application\Services\MetaAccessor;
use Pollora\Meta\Application\Services\MetaSchemaBuilder;
use Pollora\Meta\Application\Services\MetaSchemaRepository;
use Pollora\Meta\Domain\Services\MetaValueCaster;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Tests\Unit\BlockBinding\Fixtures\ArrayMetaStore;

beforeEach(function (): void {
    $this->app = new Application(sys_get_temp_dir());
    $this->app->instance('config', new Repository(['app' => ['debug' => false]]));
    $this->app->instance(LoggerInterface::class, Mockery::mock(LoggerInterface::class));
    $this->app->instance(Action::class, Mockery::mock(Action::class));
    $this->app->instance(MetaAccessor::class, new MetaAccessor(new MetaSchemaRepository, new MetaSchemaBuilder, new ArrayMetaStore, new MetaValueCaster));
    (new BlockBindingServiceProvider($this->app))->register();
});

it('binds the discovery and one resolver for the request', function (): void {
    expect($this->app->make(BlockBindingDiscovery::class))->toBeInstanceOf(BlockBindingDiscovery::class)
        ->and($this->app->make(BindingResolver::class))->toBe($this->app->make(BindingResolver::class))
        ->and($this->app->make('config')->get('block-bindings.options'))->toBe([]);
});

it('registers the sources Pollora ships, and their editor side', function (): void {
    $registered = [];
    $hooks = [];
    $this->app->make(Action::class)->shouldReceive('add')->andReturnUsing(function (string $hook) use (&$hooks): Action {
        $hooks[] = $hook;

        return $this->app->make(Action::class);
    });
    Functions\when('did_action')->justReturn(1);
    Functions\when('get_block_bindings_source')->justReturn();
    Functions\when('register_block_bindings_source')->alias(function (string $name, array $properties) use (&$registered): bool {
        $registered[$name] = $properties['uses_context'];

        return true;
    });

    (new BlockBindingServiceProvider($this->app))->boot();

    expect($registered)->toBe([
        'pollora/post-meta' => ['postId', 'postType'],
        'pollora/term-meta' => ['termId', 'taxonomy'],
        'pollora/author-meta' => ['postId'],
        'pollora/option' => [],
    ])->and($this->app->make(BindingSourceRegistry::class)->all())->toHaveCount(4)
        ->and($hooks)->toBe(['enqueue_block_editor_assets', 'rest_api_init']);
});

it('generates a source named after the application, never after the framework', function (): void {
    $basePath = sys_get_temp_dir().'/pollora-binding-'.uniqid();
    mkdir($basePath.'/app', 0755, true);
    file_put_contents($basePath.'/composer.json', json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]));
    $this->app->setBasePath($basePath);
    Container::setInstance($this->app);
    $command = new MakeBindingCommand(new Filesystem);
    $command->setLaravel($this->app);
    (new ReflectionProperty(Command::class, 'input'))->setValue($command, new ArrayInput(['name' => 'ConcertBinding'], $command->getDefinition()));
    $stub = file_get_contents((new ReflectionMethod($command, 'getStub'))->invoke($command));

    $class = (new ReflectionMethod($command, 'replaceClass'))->invoke($command, $stub, 'App\\Cms\\Bindings\\ConcertBinding');

    expect($class)->toContain("#[BlockBinding('app/concert', label: 'Concert')]")
        ->toContain('final class ConcertBinding')
        ->and($command->getName())->toBe('pollora:make:binding');

    (new Filesystem)->deleteDirectory($basePath);
    Container::setInstance(new Container);
});
