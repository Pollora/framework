<?php

declare(strict_types=1);

namespace Pollora\Attributes\Taxonomy;

use Attribute;
use Pollora\Attributes\Attributable;
use Pollora\Attributes\Contracts\HandlesAttributes;
use ReflectionClass;
use ReflectionMethod;

/**
 * Test attributes that take over the handling of their own configuration, the way a project attribute can.
 * They live in the namespace discovery scans for built-in class-level attributes.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ZzDiscoveryHandlesClass implements HandlesAttributes
{
    public function handle($container, Attributable $instance, ReflectionClass|ReflectionMethod $context, object $attribute): void
    {
        $instance->replaceArgs([...$instance->withArgs(), 'handled_class' => $context->getShortName()]);
    }
}

#[Attribute(Attribute::TARGET_CLASS)]
final class ZzDiscoveryBrokenOnClass
{
    public function __construct()
    {
        throw new \RuntimeException('class attribute is broken');
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class ZzDiscoveryHandlesMethod implements HandlesAttributes
{
    public function handle($container, Attributable $instance, ReflectionClass|ReflectionMethod $context, object $attribute): void
    {
        $instance->replaceArgs([...$instance->withArgs(), 'handled_method' => $context->getName()]);
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class ZzDiscoveryBrokenOnMethod
{
    public function __construct()
    {
        throw new \RuntimeException('method attribute is broken');
    }
}
