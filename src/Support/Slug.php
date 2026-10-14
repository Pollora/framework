<?php

declare(strict_types=1);

namespace Pollora\Support;

use Pollora\Attributes\Support\Naming;

/**
 * Utility class for slug generation.
 *
 * This class provides methods to generate slugs from class names and method names
 * following WordPress CLI conventions (kebab-case).
 */
class Slug
{
    /**
     * Generate a command slug from a class name.
     *
     * Examples:
     * - UserManagementCommand -> user-management-command
     * - TestCommand -> test-command
     * - UserCommand -> user-command
     *
     * @param  string  $className  The class name (can be fully qualified)
     * @return string The generated slug
     */
    public static function fromClassName(string $className): string
    {
        return Naming::commandFromClassName($className);
    }

    /**
     * Generate a subcommand slug from a method name.
     *
     * Examples:
     * - createUser -> create-user
     * - listItems -> list-items
     * - deleteById -> delete-by-id
     * - run -> run
     *
     * @param  string  $methodName  The method name
     * @return string The generated slug
     */
    public static function fromMethodName(string $methodName): string
    {
        return Naming::slug($methodName);
    }

    /**
     * Convert a string to kebab-case.
     *
     * @param  string  $string  The string to convert
     * @return string The kebab-case string
     */
    public static function toKebabCase(string $string): string
    {
        return Naming::slug($string);
    }
}
