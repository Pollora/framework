<?php

declare(strict_types=1);

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Pollora\Events\WordPress\Option\OptionEventDispatcher;
use Pollora\Events\WordPress\Plugins\YoastSeo\YoastSeoEventDispatcher;
use Pollora\Events\WordPress\Post\PostEventDispatcher;
use Pollora\Events\WordPress\WordPressEventServiceProvider;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\HookInterface;

require_once __DIR__.'/fixtures.php';

beforeEach(function (): void {
    $this->hooks = new ArrayObject;

    $action = Mockery::mock(Action::class);
    $action->shouldReceive('add')->andReturnUsing(function (string $hook, callable|array $callback, int $priority = 10, int $args = 1) {
        $this->hooks[] = compact('hook', 'callback', 'priority', 'args');

        return Mockery::mock(HookInterface::class);
    });

    $this->app = new Application(sys_get_temp_dir());
    $this->app->instance(Dispatcher::class, Mockery::mock(Dispatcher::class));
    $this->app->instance(Action::class, $action);

    $this->provider = new WordPressEventServiceProvider($this->app);
});

it('shares one instance of each dispatcher', function (): void {
    $this->provider->register();

    expect($this->app->make(PostEventDispatcher::class))->toBe($this->app->make(PostEventDispatcher::class))
        ->and($this->app->make(OptionEventDispatcher::class))->toBe($this->app->make(OptionEventDispatcher::class));
});

it('registers the WordPress hooks of every dispatcher on boot', function (): void {
    $this->provider->register();
    $this->provider->boot();

    $registered = collect($this->hooks)->mapWithKeys(fn (array $hook): array => [$hook['hook'].'|'.$hook['callback'][0]::class => $hook['callback'][1]]);

    expect($registered['transition_post_status|'.PostEventDispatcher::class])->toBe('handleTransitionPostStatus')
        ->and($registered['deleted_post|'.PostEventDispatcher::class])->toBe('handleDeletedPost')
        ->and($registered['updated_option|'.OptionEventDispatcher::class])->toBe('handleUpdatedOption')
        ->and($registered['seo_page_wpseo_files|'.YoastSeoEventDispatcher::class])->toBe('handleSeoPageWpseoFiles');
});

it('registers each hook on the shared dispatcher instance', function (): void {
    $this->provider->register();
    $this->provider->boot();

    $post = collect($this->hooks)->first(fn (array $hook): bool => $hook['hook'] === 'deleted_post');

    expect($post['callback'][0])->toBe($this->app->make(PostEventDispatcher::class))
        ->and($post['priority'])->toBe(10)
        ->and($post['args'])->toBe(5);
});

it('only registers hooks that have a handler to call', function (): void {
    $this->provider->register();
    $this->provider->boot();

    $uncallable = collect($this->hooks)
        ->reject(fn (array $hook): bool => is_callable($hook['callback']))
        ->map(fn (array $hook): string => $hook['callback'][0]::class.'::'.$hook['callback'][1])
        ->all();

    expect($uncallable)->toBe([])
        ->and(count($this->hooks))->toBeGreaterThan(40);
});
