<?php

declare(strict_types=1);

namespace Pollen\Ajax;

class AjaxFactory
{
    public function listen(string $action, callable|string $callback)
    {
        return new Ajax($action, $callback);
    }
}
