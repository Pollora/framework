<?php

declare(strict_types=1);

namespace Pollora\Attributes\Contracts;

use Pollora\Attributes\Attributable;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;

interface HandlesAttributes
{
    public function handle(ContainerInterface $container, Attributable $instance, ReflectionClass|ReflectionMethod $context, object $attribute): void;
}
