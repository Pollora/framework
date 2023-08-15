<?php

declare(strict_types=1);

namespace Pollen\Support;

use Illuminate\Support\Str;

/**
 * The ArgumentHelper class is a trait that provides methods to extract arguments from properties using getter methods.
 */
trait WordPressArgumentHelper
{
    /**
     * Collects all getter methods of the current object.
     *
     * @return array An array containing all getter method names.
     */
    private function collectGetters(): array
    {
        $allMethods = get_class_methods($this);

        return array_filter($allMethods, function ($method) {
            return str_starts_with($method, 'get') && $method !== 'getArgs';
        });
    }

    /**
     * Generate argument name from given getter method
     *
     * @return string Argument's name in snake_case format
     */
    private function makeArgName(string $getter): string
    {
        $propertyName = substr($getter, 3);

        return Str::snake($propertyName);
    }

    /**
     * Extracts arguments from object properties using getter methods.
     *
     * @return array An associative array containing the extracted arguments.
     */
    public function extractArgumentFromProperties()
    {
        $args = [];
        $getters = $this->collectGetters();

        foreach ($getters as $getter) {
            $argValue = $this->{$getter}();
            if ($argValue === null) {
                continue;
            }
            $args[$this->makeArgName($getter)] = $this->{$getter}();
        }

        return $args;
    }
}
