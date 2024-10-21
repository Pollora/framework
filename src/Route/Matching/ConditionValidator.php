<?php

declare(strict_types=1);

namespace Pollora\Route\Matching;

use Illuminate\Http\Request;
use Illuminate\Routing\Matching\ValidatorInterface;
use Illuminate\Routing\Route;

class ConditionValidator implements ValidatorInterface
{
    public function matches(Route $route, Request $request): bool
    {
        return call_user_func_array($route->getCondition(), $route->getConditionParameters());
    }
}
