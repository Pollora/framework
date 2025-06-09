<?php

declare(strict_types=1);

namespace Pollora\Ajax\Domain\Models;

use Pollora\Ajax\Application\Services\RegisterAjaxActionService;
use Pollora\Ajax\Domain\Exceptions\InvalidAjaxActionException;

/**
 * Domain entity representing an AJAX action definition.
 * Contains the action name, callback, user type, and registration logic.
 */
class AjaxAction
{
    /**
     * User type constant for both logged and guest users.
     */
    public const BOTH_USERS = 'both';

    /**
     * User type constant for logged-in users only.
     */
    public const LOGGED_USERS = 'logged';

    /**
     * User type constant for guest users only.
     */
    public const GUEST_USERS = 'guest';

    private string $userType = self::BOTH_USERS;

    private ?RegisterAjaxActionService $registerService;

    /**
     * AjaxAction constructor.
     *
     * @param  string  $name  The action name.
     * @param  callable|string  $callback  The callback to execute.
     * @param  RegisterAjaxActionService|null  $registerService  The application service for registration.
     *
     * @throws InvalidAjaxActionException
     */
    public function __construct(
        /**
         * @var string $name The action name.
         */
        private readonly string $name,
        /**
         * @var callable|string $callback The callback to execute.
         */
        private readonly mixed $callback,
        ?RegisterAjaxActionService $registerService = null
    ) {
        if (empty($this->name) || empty($this->callback)) {
            throw new InvalidAjaxActionException('Action and callback must be provided.');
        }
        $this->registerService = $registerService;
    }

    /**
     * Set the user type for this action.
     *
     * @return $this
     */
    public function setUserType(string $userType): static
    {
        $this->userType = $userType;

        return $this;
    }

    /**
     * Get the user type for this action.
     */
    public function getUserType(): string
    {
        return $this->userType;
    }

    /**
     * Restrict the action to logged-in users only.
     *
     * @return $this
     */
    public function forLoggedUsers(): static
    {
        $this->setUserType(self::LOGGED_USERS);

        return $this;
    }

    /**
     * Restrict the action to guest users only.
     *
     * @return $this
     */
    public function forGuestUsers(): static
    {
        $this->setUserType(self::GUEST_USERS);

        return $this;
    }

    /**
     * Check if the action is for both or logged-in users.
     */
    public function isBothOrLoggedUsers(): bool
    {
        if ($this->getUserType() === self::BOTH_USERS) {
            return true;
        }

        return $this->getUserType() === self::LOGGED_USERS;
    }

    /**
     * Check if the action is for both or guest users.
     */
    public function isBothOrGuestUsers(): bool
    {
        if ($this->getUserType() === self::BOTH_USERS) {
            return true;
        }

        return $this->getUserType() === self::GUEST_USERS;
    }

    /**
     * Get the action name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the callback for this action.
     *
     * @return callable|string
     */
    public function getCallback(): mixed
    {
        return $this->callback;
    }

    /**
     * Destructor. Registers the action using the application service if available.
     */
    public function __destruct()
    {
        if ($this->registerService) {
            $this->registerService->execute($this);
        }
    }
}
