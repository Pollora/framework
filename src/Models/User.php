<?php

declare(strict_types=1);

namespace Pollen\Modelss;

/**
 * Class User.
 */
class User extends \Corcel\Model\User implements AuthenticatableContract
{
    use Authenticatable, Rememberable;
}
