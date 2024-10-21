<?php

declare(strict_types=1);

namespace Pollora\Ajax;

class AjaxFactory
{
    public function listen(string $action, callable|string $callback): \Pollora\Ajax\Ajax
    {
        return new Ajax($action, $callback);
    }
}
