<?php

declare(strict_types=1);

require_once __DIR__.'/../helpers.php';

use Mockery as m;
use Pollora\Ajax\Domain\Models\AjaxAction;
use Pollora\Ajax\Infrastructure\Repositories\WordPressAjaxActionRegistrar;
use Pollora\Container\Domain\ServiceLocator;
use Pollora\Hook\Infrastructure\Services\Action;

beforeEach(function () {
    // Patch the Action facade statically for all tests in this file
    // Action::swap(new MockActionFacade);
    $GLOBALS['pollora_action_calls'] = [];
});

describe('WordPressAjaxActionRegistrar', function () {
    it('registers both hooks for BOTH_USERS', function () {
        $mockLocator = m::mock(ServiceLocator::class);
        $mockAction = m::mock(Action::class);
        $mockAction->shouldReceive('add')->andReturn($mockAction)->twice();
        $mockLocator->shouldReceive('resolve')->with(Action::class)->andReturn($mockAction);
        $registrar = new WordPressAjaxActionRegistrar($mockLocator);
        $action = (new AjaxAction('my_action', function () {}));
        $registrar->register($action);
    });

    it('registers only wp_ajax for LOGGED_USERS', function () {
        $mockLocator = m::mock(ServiceLocator::class);
        $mockAction = m::mock(Action::class);
        $mockAction->shouldReceive('add')->andReturn($mockAction)->once();
        $mockLocator->shouldReceive('resolve')->with(Action::class)->andReturn($mockAction);
        $registrar = new WordPressAjaxActionRegistrar($mockLocator);
        $action = (new AjaxAction('my_action', function () {}))->forLoggedUsers();
        $registrar->register($action);
    });

    it('registers only wp_ajax_nopriv for GUEST_USERS', function () {
        $mockLocator = m::mock(ServiceLocator::class);
        $mockAction = m::mock(Action::class);
        $mockAction->shouldReceive('add')->andReturn($mockAction)->once();
        $mockLocator->shouldReceive('resolve')->with(Action::class)->andReturn($mockAction);
        $registrar = new WordPressAjaxActionRegistrar($mockLocator);
        $action = (new AjaxAction('my_action', function () {}))->forGuestUsers();
        $registrar->register($action);
    });
});
