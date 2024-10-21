<?php

declare(strict_types=1);
/**
 * Class Ajax
 *
 * This class is responsible for registering AJAX actions and callbacks in WordPress.
 */

namespace Pollora\Ajax;

use InvalidArgumentException;
use Pollora\Support\Facades\Action;

/**
 * Class Ajax
 *
 * The Ajax class is used to define an AJAX action in WordPress for both logged-in and guest users.
 */
class Ajax
{
    /**
     * The constant representing the type of users that can be both guests and logged users.
     *
     * @var string
     */
    const BOTH_USERS = 'both';

    /**
     * Represents the user role for logged users.
     */
    const LOGGED_USERS = 'logged';

    /**
     * Represents the user role for guest users.
     */
    const GUEST_USERS = 'guest';

    /**
     * User types by default (both).
     */
    private string $userType = self::BOTH_USERS;

    /**
     * Constructs a new instance of the class.
     *
     * @param  string  $action  The action to be performed.
     * @param  callable|string  $callback  The callback function to be executed.
     *
     * @throws InvalidArgumentException If either the action or callback is empty.
     */
    public function __construct(
        /**
         * @var string $action The current action being performed. Can be null if no specific action is set.
         */
        private $action,
        /**
         * Represents a callback function that can be executed.
         *
         * @var callable|string $callback The callback function to be executed.
         */
        private $callback
    ) {
        if (empty($this->action) || empty($this->callback)) {
            throw new InvalidArgumentException('Action and callback must be provided.');
        }
    }

    /**
     * Sets the user type.
     *
     * @param  string  $userType  The user type to set.
     * @return static Returns the object instance for method chaining.
     */
    public function setUserType(string $userType): static
    {
        $this->userType = $userType;

        return $this;
    }

    /**
     * Gets the type of the user.
     *
     * @return string The user type.
     */
    public function getUserType(): string
    {
        return $this->userType;
    }

    /**
     * Sets the ajax action only for logged users.
     */
    public function forLoggedUsers(): static
    {
        $this->setUserType(self::LOGGED_USERS);

        return $this;
    }

    /**
     * Sets the ajax action only for guest users.
     *
     * @return static Returns the current instance of the class.
     */
    public function forGuestUsers(): static
    {
        $this->setUserType(self::GUEST_USERS);

        return $this;
    }

    /**
     * The destructor adds action hooks for both logged in and guest users based on the user type set in the class.
     * If the user type is set to both users or logged in users, it adds an action hook for wp_ajax_{action} with the callback function.
     * If the user type is set to both users or guest users, it adds an action hook for wp_ajax_nopriv_{action} with the callback function.
     *
     * @return void
     */
    public function __destruct()
    {
        $this->registerAjaxForUserType();
    }

    /**
     * Registers the ajax action based on the user type.
     *
     * This method registers the ajax action for either logged-in users or guest users,
     * depending on the user type specified in the current object.
     */
    private function registerAjaxForUserType(): void
    {
        if ($this->isBothOrLoggedUsers()) {
            Action::add('wp_ajax_'.$this->action, $this->callback);
        }

        if ($this->isBothOrGuestUsers()) {
            Action::add('wp_ajax_nopriv_'.$this->action, $this->callback);
        }
    }

    /**
     * Checks if the user type is either both users or logged users.
     *
     * @return bool True if the user type is both users or logged users, false otherwise.
     */
    private function isBothOrLoggedUsers(): bool
    {
        if ($this->getUserType() === self::BOTH_USERS) {
            return true;
        }
        return $this->getUserType() === self::LOGGED_USERS;
    }

    /**
     * Checks if the user type is either BOTH_USERS or GUEST_USERS.
     *
     * @return bool Returns true if the user type is BOTH_USERS or GUEST_USERS, otherwise false.
     */
    private function isBothOrGuestUsers(): bool
    {
        if ($this->getUserType() === self::BOTH_USERS) {
            return true;
        }
        return $this->getUserType() === self::GUEST_USERS;
    }
}
