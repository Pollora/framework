<?php

declare(strict_types=1);

namespace Pollora\Support;

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
        // Extract the base class name (remove namespace)
        $baseName = basename(str_replace('\\', '/', $className));

        // Remove "Command" suffix if present
        if (self::endsWith($baseName, 'Command')) {
            $baseName = substr($baseName, 0, -7);
        }

        return self::toKebabCase($baseName);
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
        return self::toKebabCase($methodName);
    }

    /**
     * Convert a string to kebab-case.
     *
     * @param  string  $string  The string to convert
     * @return string The kebab-case string
     */
    public static function toKebabCase(string $string): string
    {
        // Insert a hyphen before any uppercase letter that follows a lowercase letter or number
        $string = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $string);

        // Convert to lowercase
        $string = strtolower($string);

        // Replace multiple consecutive hyphens with a single hyphen
        $string = preg_replace('/-+/', '-', $string);

        // Trim hyphens from the beginning and end
        $string = trim($string, '-');

        return $string;
    }

    /**
     * Check if a string ends with a specific suffix.
     *
     * @param  string  $haystack  The string to check
     * @param  string  $needle  The suffix to look for
     * @return bool True if the string ends with the suffix
     */
    private static function endsWith(string $haystack, string $needle): bool
    {
        $length = strlen($needle);
        if ($length === 0) {
            return true;
        }

        return substr($haystack, -$length) === $needle;
    }
}
