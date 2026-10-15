<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Config\Repository;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Pollora\BlockBinding\Application\Services\BindingSourceRegistry;
use Pollora\BlockBinding\Domain\Contracts\ContentVisibilityInterface;
use Pollora\BlockBinding\Domain\Contracts\ValuePresenterInterface;
use Pollora\BlockBinding\Domain\Models\BindingContext;
use Pollora\BlockBinding\Infrastructure\Providers\BlockBindingServiceProvider;
use Pollora\BlockBinding\Infrastructure\Services\BindingEditorData;
use Pollora\BlockBinding\Infrastructure\Sources\OptionSource;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Meta\Application\Services\MetaSchemaBuilder;
use Pollora\Meta\Application\Services\MetaSchemaRepository;
use Pollora\Meta\Domain\Contracts\MetaStoreInterface;
use Pollora\Meta\Infrastructure\Providers\MetaServiceProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tests\Unit\BlockBinding\Fixtures\ArrayMetaStore;
use Tests\Unit\BlockBinding\Fixtures\ArtistProfile;
use Tests\Unit\BlockBinding\Fixtures\Concert;
use Tests\Unit\BlockBinding\Fixtures\FakePresenter;
use Tests\Unit\BlockBinding\Fixtures\FakeVisibility;
use Tests\Unit\BlockBinding\Fixtures\VenueExtras;

require_once dirname(__DIR__, 2).'/Unit/BlockBinding/Fixtures/blocks.php';

/**
 * Block Bindings with the real provider and the real Laravel container, over the typed meta of Meta: the sources
 * Pollora ships, what the editor is told, and the options a project lists in `config/block-bindings.php`. It
 * freezes what the extraction of `pollora/block-bindings` must keep, in particular that the options reach the
 * sources and the editor data through the container (they must not turn into silent `null`s).
 */
beforeEach(function (): void {
    \Brain\Monkey\setUp();
    Functions\when('did_action')->justReturn(0);
    Functions\when('number_format_i18n')->alias(fn (float|int $number, int $decimals = 0): string => number_format((float) $number, $decimals, ',', ' '));
    Functions\when('get_block_bindings_source')->justReturn();
    Functions\when('get_option')->alias(fn (string $name): string => $name === 'blogdescription' ? 'Live music' : 'secret');

    $this->registered = [];
    Functions\when('register_block_bindings_source')->alias(function (string $name, array $args): void {
        $this->registered[$name] = $args;
    });

    $this->app = new Application(sys_get_temp_dir());
    $this->app->instance('config', new Repository(['app' => ['debug' => false], 'block-bindings' => ['options' => ['blogdescription']]]));
    $this->app->instance(LoggerInterface::class, new NullLogger);
    $this->app->instance('events', new Dispatcher);
    $this->app->instance('validator', new Factory(new Translator(new ArrayLoader, 'en')));

    $this->hooks = [];
    $action = Mockery::mock(Action::class);
    $action->shouldReceive('add')->andReturnUsing(function (string $hook, Closure $callback) use ($action): Action {
        $this->hooks[$hook][] = $callback;

        return $action;
    });
    $this->app->instance(Action::class, $action);
    $this->app->instance(Filter::class, Mockery::mock(Filter::class)->shouldIgnoreMissing());

    (new MetaServiceProvider($this->app))->register();
    $this->provider = new BlockBindingServiceProvider($this->app);
    $this->provider->register();

    $this->app->instance(MetaStoreInterface::class, new ArrayMetaStore(['post' => [7 => ['capacity' => '1200']], 'user' => [5 => ['stage_name' => 'The Hums', 'phone' => '0600000000']]]));
    $this->app->instance(ContentVisibilityInterface::class, new FakeVisibility);
    $this->app->instance(ValuePresenterInterface::class, new FakePresenter);

    $schemas = $this->app->make(MetaSchemaRepository::class);
    foreach ([Concert::class, ArtistProfile::class, VenueExtras::class] as $class) {
        $schemas->add($this->app->make(MetaSchemaBuilder::class)->build($class));
    }

    $this->provider->boot();
});

afterEach(function (): void {
    \Brain\Monkey\tearDown();
});

it('registers the four sources Pollora ships, with WordPress, on init', function (): void {
    foreach ($this->hooks['init'] as $register) {
        $register();
    }

    expect(array_keys($this->registered))->toBe(['pollora/post-meta', 'pollora/term-meta', 'pollora/author-meta', 'pollora/option'])
        ->and($this->app->make(BindingSourceRegistry::class)->find('pollora/post-meta')?->name)->toBe('pollora/post-meta');
});

it('lists the options of the project, through the container, in the editor data', function (): void {
    $data = $this->app->make(BindingEditorData::class)->toArray();

    expect($data['route'])->toBe('pollora/v1/block-bindings/resolve')
        ->and($data['sources']['pollora/option']['fields'])->toBe([['label' => 'Blogdescription', 'args' => ['name' => 'blogdescription'], 'type' => 'string']])
        ->and(array_column(array_column($data['sources']['pollora/author-meta']['fields'][''], 'args'), 'key'))->toBe(['stage_name'])
        ->and(array_keys($data['sources']['pollora/post-meta']['fields']))->toContain('concert');
});

it('reads only the listed options, through the container', function (): void {
    $source = $this->app->make(OptionSource::class);

    expect($source(new BindingContext(args: ['name' => 'blogdescription'])))->toBe('Live music')
        ->and($source(new BindingContext(args: ['name' => 'admin_email'])))->toBeNull();
});

it('resolves a post meta, an author meta and an unlisted key through the resolver the container builds', function (): void {
    foreach ($this->hooks['init'] as $register) {
        $register();
    }

    $block = boundParagraph(['postId' => 7, 'postType' => 'concert']);

    $resolve = fn (string $source, array $args): mixed => $this->registered[$source]['get_value_callback']($args, $block, 'content');

    expect($resolve('pollora/post-meta', ['key' => 'capacity']))->toBe('1 200')
        ->and($resolve('pollora/post-meta', ['key' => 'backstage_code']))->toBeNull()
        ->and($resolve('pollora/option', ['name' => 'blogdescription']))->toBe('Live music')
        ->and($resolve('pollora/option', ['name' => 'admin_email']))->toBeNull();
});
