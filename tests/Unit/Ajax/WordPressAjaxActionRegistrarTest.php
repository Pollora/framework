<?php

declare(strict_types=1);

use Mockery as m;
use Pollora\Ajax\Domain\Models\AjaxAction;
use Pollora\Ajax\Infrastructure\Repositories\WordPressAjaxActionRegistrar;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Support\Facades\Action as ActionFacade;
use Psr\Container\ContainerInterface;

beforeEach(function (): void {
    // Patch the Action facade statically for all tests in this file
    ActionFacade::swap(new class
    {
        public array $calls = [];

        public function add($hook, $callback): void
        {
            $GLOBALS['pollora_action_calls'][] = [$hook, $callback];
        }
    });
    $GLOBALS['pollora_action_calls'] = [];
});

afterEach(function (): void {
    m::close();
});

describe('WordPressAjaxActionRegistrar', function (): void {
    it('registers both hooks for BOTH_USERS', function (): void {
        $container = m::mock(ContainerInterface::class);
        $actionService = m::mock(Action::class);
        $actionService->shouldReceive('add')->andReturnUsing(function ($hook, $callback) use ($actionService) {
            $GLOBALS['pollora_action_calls'][] = [$hook, $callback];

            return $actionService;
        });
        $container->shouldReceive('get')->with(Action::class)->andReturn($actionService);
        $registrar = new WordPressAjaxActionRegistrar($container);
        $action = (new AjaxAction('my_action', function (): void {}));
        $registrar->register($action);
        expect($GLOBALS['pollora_action_calls'])->toContain(['wp_ajax_my_action', $action->getCallback()])
            ->and($GLOBALS['pollora_action_calls'])->toContain(['wp_ajax_nopriv_my_action', $action->getCallback()]);
    });

    it('registers only wp_ajax for LOGGED_USERS', function (): void {
        $container = m::mock(ContainerInterface::class);
        $actionService = m::mock(Action::class);
        $actionService->shouldReceive('add')->andReturnUsing(function ($hook, $callback) use ($actionService) {
            $GLOBALS['pollora_action_calls'][] = [$hook, $callback];

            return $actionService;
        });
        $container->shouldReceive('get')->with(Action::class)->andReturn($actionService);
        $registrar = new WordPressAjaxActionRegistrar($container);
        $action = (new AjaxAction('my_action', function (): void {}))->forLoggedUsers();
        $registrar->register($action);
        expect($GLOBALS['pollora_action_calls'])->toContain(['wp_ajax_my_action', $action->getCallback()])
            ->and($GLOBALS['pollora_action_calls'])->not->toContain(['wp_ajax_nopriv_my_action', $action->getCallback()]);
    });

    it('registers only wp_ajax_nopriv for GUEST_USERS', function (): void {
        $container = m::mock(ContainerInterface::class);
        $actionService = m::mock(Action::class);
        $actionService->shouldReceive('add')->andReturnUsing(function ($hook, $callback) use ($actionService) {
            $GLOBALS['pollora_action_calls'][] = [$hook, $callback];

            return $actionService;
        });
        $container->shouldReceive('get')->with(Action::class)->andReturn($actionService);
        $registrar = new WordPressAjaxActionRegistrar($container);
        $action = (new AjaxAction('my_action', function (): void {}))->forGuestUsers();
        $registrar->register($action);
        expect($GLOBALS['pollora_action_calls'])->not->toContain(['wp_ajax_my_action', $action->getCallback()])
            ->and($GLOBALS['pollora_action_calls'])->toContain(['wp_ajax_nopriv_my_action', $action->getCallback()]);
    });
});
