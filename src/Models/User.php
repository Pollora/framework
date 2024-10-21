<?php

declare(strict_types=1);

namespace Pollora\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Watson\Rememberable\Rememberable;

/**
 * Class User.
 *
 * @property int $ID
 * @property string $user_login
 * @property string $user_pass
 * @property string $user_nicename
 * @property string $user_email
 * @property string $user_url
 * @property string $user_registered
 * @property string $user_activation_key
 * @property int $user_status
 * @property string $display_name
 */
class User extends \Corcel\Model\User implements AuthenticatableContract
{
    use Authenticatable, Rememberable;

    /**
     * Convert the User instance to a WP_User object.
     */
    public function toWpUser(): \WP_User
    {
        return new \WP_User($this->ID);
    }
}
