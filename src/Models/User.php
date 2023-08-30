<?php

declare(strict_types=1);

namespace Pollen\Models;

use Illuminate\Auth\Authenticatable;
use Watson\Rememberable\Rememberable;

/**
 * Class User.
 */
class User extends \Corcel\Model\User
{
    use Authenticatable, Rememberable;
}
