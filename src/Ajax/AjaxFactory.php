<?php

declare(strict_types=1);

namespace Pollen\Ajax;

class AjaxFactory
{
    public function listen($action, $callback)
    {
        return new Ajax($action, $callback);
    }
}
