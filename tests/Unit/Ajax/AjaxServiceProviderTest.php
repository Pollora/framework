<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Pollora\Ajax\Adapter\Out\WordPress\WordPressAjaxActionRegistrar;
use Pollora\Ajax\Application\Service\RegisterAjaxActionService;
use Pollora\Ajax\Factory\AjaxFactory;
use Pollora\Ajax\Infrastructure\Providers\AjaxServiceProvider;
use Pollora\Ajax\Infrastructure\Services\AjaxDiscovery;
use Pollora\Ajax\Port\Out\AjaxActionRegistrarPort;

// Building an Application replaces the global container: put the previous one back.
beforeEach(function (): void {
    $this->previousContainer = Container::getInstance();
});

afterEach(function (): void {
    Container::setInstance($this->previousContainer);
});

function ajaxProviderApp(): Application
{
    $app = new Application(sys_get_temp_dir());
    (new AjaxServiceProvider($app))->register();

    return $app;
}

it('binds the registrar port to the WordPress registrar', function (): void {
    expect(ajaxProviderApp()->make(AjaxActionRegistrarPort::class))->toBeInstanceOf(WordPressAjaxActionRegistrar::class);
});

it('exposes the factory as wp.ajax, built on the shared registration service', function (): void {
    $app = ajaxProviderApp();

    expect($app->make('wp.ajax'))->toBeInstanceOf(AjaxFactory::class)
        ->and($app->make('wp.ajax'))->toBe($app->make('wp.ajax'))
        ->and($app->make(RegisterAjaxActionService::class))->toBe($app->make(RegisterAjaxActionService::class));
});

it('builds the discovery on the wp.ajax factory', function (): void {
    $app = ajaxProviderApp();

    expect($app->make(AjaxDiscovery::class))->toBeInstanceOf(AjaxDiscovery::class)
        ->and($app->make(AjaxDiscovery::class))->toBe($app->make(AjaxDiscovery::class));
});

it('prints the Pollora.ajaxurl global in the head, before other scripts', function (): void {
    $hooks = [];
    Functions\when('add_action')->alias(function (string $hook, callable $callback, int $priority = 10) use (&$hooks): bool {
        $hooks[] = [$hook, $callback, $priority];

        return true;
    });
    Functions\when('admin_url')->alias(fn (string $path): string => 'https://example.test/wp-admin/'.$path);

    $app = ajaxProviderApp();
    (new AjaxServiceProvider($app))->boot();

    expect($hooks)->toHaveCount(1)
        ->and($hooks[0][0])->toBe('wp_head')
        ->and($hooks[0][2])->toBe(1);

    ob_start();
    ($hooks[0][1])();
    $output = ob_get_clean();

    expect($output)->toContain('var Pollora = { ajaxurl: "https://example.test/wp-admin/admin-ajax.php" };');
});
