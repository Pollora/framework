<?php

declare(strict_types=1);

use Pollora\Ajax\Domain\Models\AjaxAction;
use Pollora\Ajax\Infrastructure\Repositories\WordPressAjaxActionRegistrar;
use Pollora\Support\Facades\Action;

beforeEach(function () {
    // Patch the Action facade statically for all tests in this file
    Action::swap(new class
    {
        public array $calls = [];

        public function add($hook, $callback)
        {
            $GLOBALS['pollora_action_calls'][] = [$hook, $callback];
        }
    });
    $GLOBALS['pollora_action_calls'] = [];
});

describe('WordPressAjaxActionRegistrar', function () {
    it('registers both hooks for BOTH_USERS', function () {
        $registrar = new WordPressAjaxActionRegistrar;
        $action = (new AjaxAction('my_action', function () {}));
        $registrar->register($action);
        expect($GLOBALS['pollora_action_calls'])->toContain(['wp_ajax_my_action', $action->getCallback()])
            ->and($GLOBALS['pollora_action_calls'])->toContain(['wp_ajax_nopriv_my_action', $action->getCallback()]);
    });

    it('registers only wp_ajax for LOGGED_USERS', function () {
        $registrar = new WordPressAjaxActionRegistrar;
        $action = (new AjaxAction('my_action', function () {}))->forLoggedUsers();
        $registrar->register($action);
        expect($GLOBALS['pollora_action_calls'])->toContain(['wp_ajax_my_action', $action->getCallback()])
            ->and($GLOBALS['pollora_action_calls'])->not->toContain(['wp_ajax_nopriv_my_action', $action->getCallback()]);
    });

    it('registers only wp_ajax_nopriv for GUEST_USERS', function () {
        $registrar = new WordPressAjaxActionRegistrar;
        $action = (new AjaxAction('my_action', function () {}))->forGuestUsers();
        $registrar->register($action);
        expect($GLOBALS['pollora_action_calls'])->not->toContain(['wp_ajax_my_action', $action->getCallback()])
            ->and($GLOBALS['pollora_action_calls'])->toContain(['wp_ajax_nopriv_my_action', $action->getCallback()]);
    });
});
