<?php

declare(strict_types=1);

namespace Pollora\Hook;

use ReflectionClass;
use ReflectionMethod;
use Pollora\Attributes\Action as ActionAttribute;
use Pollora\Attributes\Filter as FilterAttribute;
use Pollora\Support\Facades\Action;
use Pollora\Support\Facades\Filter;

class HookRegistrar
{
    /**
     * Scan a class for methods with Action or Filter attributes
     * and register them with WordPress.
     *
     * @param object $instance The instance of the class to scan
     * @return void
     */
    public static function registerHooks(object $instance): void
    {
        $reflection = new ReflectionClass($instance);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes() as $attribute) {
                $attributeInstance = $attribute->newInstance();

                if ($attributeInstance instanceof ActionAttribute) {
                    Action::add(
                        $attributeInstance->hook,
                        [$instance, $method->getName()],
                        $attributeInstance->priority,
                        self::getNumberOfParameters($method)
                    );
                }

                if ($attributeInstance instanceof FilterAttribute) {
                    Filter::add(
                        $attributeInstance->hook,
                        [$instance, $method->getName()],
                        $attributeInstance->priority,
                        self::getNumberOfParameters($method)
                    );
                }
            }
        }
    }

    /**
     * Get the number of parameters expected by a method.
     *
     * @param ReflectionMethod $method
     * @return int
     */
    protected static function getNumberOfParameters(ReflectionMethod $method): int
    {
        return $method->getNumberOfParameters();
    }
}
