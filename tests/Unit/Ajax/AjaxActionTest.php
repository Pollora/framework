<?php

declare(strict_types=1);

use Pollora\Ajax\Application\Services\RegisterAjaxActionService;
use Pollora\Ajax\Domain\Exceptions\InvalidAjaxActionException;
use Pollora\Ajax\Domain\Models\AjaxAction;

class DummyRegisterAjaxActionService extends RegisterAjaxActionService
{
    public array $calls = [];

    public function __construct() {}

    public function execute($action): void
    {
        $this->calls[] = $action;
    }
}

describe('AjaxAction', function () {
    it('can be instantiated with valid parameters', function () {
        $action = new AjaxAction('my_action', function () {});
        expect($action->getName())->toBe('my_action')
            ->and($action->getUserType())->toBe(AjaxAction::BOTH_USERS)
            ->and(is_callable($action->getCallback()) || is_string($action->getCallback()))->toBeTrue();
    });

    it('throws exception if name or callback is empty', function () {
        expect(fn () => new AjaxAction('', function () {}))->toThrow(InvalidAjaxActionException::class)
            ->and(fn () => new AjaxAction('my_action', null))->toThrow(InvalidAjaxActionException::class);
    });

    it('can set user type to logged or guest', function () {
        $action = new AjaxAction('my_action', function () {});
        $action->forLoggedUsers();
        expect($action->getUserType())->toBe(AjaxAction::LOGGED_USERS);
        $action->forGuestUsers();
        expect($action->getUserType())->toBe(AjaxAction::GUEST_USERS);
    });

    it('isBothOrLoggedUsers and isBothOrGuestUsers logic works', function () {
        $action = new AjaxAction('my_action', function () {});
        expect($action->isBothOrLoggedUsers())->toBeTrue()
            ->and($action->isBothOrGuestUsers())->toBeTrue();
        $action->forLoggedUsers();
        expect($action->isBothOrLoggedUsers())->toBeTrue()
            ->and($action->isBothOrGuestUsers())->toBeFalse();
        $action->forGuestUsers();
        expect($action->isBothOrGuestUsers())->toBeTrue()
            ->and($action->isBothOrLoggedUsers())->toBeFalse();
    });

    it('registers via RegisterAjaxActionService on destruct', function () {
        $mockService = new DummyRegisterAjaxActionService;
        $action = new AjaxAction('my_action', function () {}, $mockService);
        unset($action);
        expect($mockService->calls)->toHaveCount(1)
            ->and($mockService->calls[0]->getName())->toBe('my_action');
    });
});
