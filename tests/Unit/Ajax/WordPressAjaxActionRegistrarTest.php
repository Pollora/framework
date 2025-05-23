<?php

declare(strict_types=1);

require_once __DIR__.'/../helpers.php';

use Mockery as m;
use Pollora\Ajax\Domain\Models\AjaxAction;
use Pollora\Ajax\Infrastructure\Repositories\WordPressAjaxActionRegistrar;
use Pollora\Hook\Infrastructure\Services\Action;
use Psr\Container\ContainerInterface;

beforeEach(function () {
    $GLOBALS['pollora_action_calls'] = [];
});

describe('WordPressAjaxActionRegistrar', function () {
    it('resolves Action from container', function () {
        $mockContainer = m::mock(ContainerInterface::class);
        $mockAction = m::mock(Action::class);
        $mockContainer->shouldReceive('get')->with(Action::class)->andReturn($mockAction);
        $registrar = new WordPressAjaxActionRegistrar($mockContainer);
        expect($registrar)->toBeInstanceOf(WordPressAjaxActionRegistrar::class);
    });

    it('registers both hooks for BOTH_USERS', function () {
        $mockContainer = m::mock(ContainerInterface::class);
        $mockAction = m::mock(Action::class);
        $mockAction->shouldReceive('add')->andReturn($mockAction)->twice();
        $mockContainer->shouldReceive('get')->with(Action::class)->andReturn($mockAction);
        $registrar = new WordPressAjaxActionRegistrar($mockContainer);
        $action = (new AjaxAction('my_action', function () {}));
        $registrar->register($action);
    });

    it('registers only wp_ajax for LOGGED_USERS', function () {
        $mockContainer = m::mock(ContainerInterface::class);
        $mockAction = m::mock(Action::class);
        $mockAction->shouldReceive('add')->andReturn($mockAction)->once();
        $mockContainer->shouldReceive('get')->with(Action::class)->andReturn($mockAction);
        $registrar = new WordPressAjaxActionRegistrar($mockContainer);
        $action = (new AjaxAction('my_action', function () {}))->forLoggedUsers();
        $registrar->register($action);
    });

    it('registers only wp_ajax_nopriv for GUEST_USERS', function () {
        $mockContainer = m::mock(ContainerInterface::class);
        $mockAction = m::mock(Action::class);
        $mockAction->shouldReceive('add')->andReturn($mockAction)->once();
        $mockContainer->shouldReceive('get')->with(Action::class)->andReturn($mockAction);
        $registrar = new WordPressAjaxActionRegistrar($mockContainer);
        $action = (new AjaxAction('my_action', function () {}))->forGuestUsers();
        $registrar->register($action);
    });
});
