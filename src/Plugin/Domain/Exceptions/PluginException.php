<?php

declare(strict_types=1);

namespace Pollora\Plugin\Domain\Exceptions;

use Exception;

/**
 * Plugin-specific exception class.
 *
 * Thrown when plugin-related operations fail, such as:
 * - Plugin activation/deactivation errors
 * - Plugin configuration issues
 * - Plugin dependency conflicts
 * - Plugin file system errors
 */
class PluginException extends Exception
{
    /**
     * Create a new plugin exception for activation failures.
     *
     * @param string $pluginName Plugin name
     * @param string $reason Reason for activation failure
     * @param int $code Error code
     * @param Exception|null $previous Previous exception
     * @return static
     */
    public static function activationFailed(string $pluginName, string $reason, int $code = 0, ?Exception $previous = null): self
    {
        return new self("Plugin '{$pluginName}' activation failed: {$reason}", $code, $previous);
    }

    /**
     * Create a new plugin exception for deactivation failures.
     *
     * @param string $pluginName Plugin name
     * @param string $reason Reason for deactivation failure
     * @param int $code Error code
     * @param Exception|null $previous Previous exception
     * @return static
     */
    public static function deactivationFailed(string $pluginName, string $reason, int $code = 0, ?Exception $previous = null): self
    {
        return new self("Plugin '{$pluginName}' deactivation failed: {$reason}", $code, $previous);
    }

    /**
     * Create a new plugin exception for configuration errors.
     *
     * @param string $pluginName Plugin name
     * @param string $configIssue Configuration issue description
     * @param int $code Error code
     * @param Exception|null $previous Previous exception
     * @return static
     */
    public static function configurationError(string $pluginName, string $configIssue, int $code = 0, ?Exception $previous = null): self
    {
        return new self("Plugin '{$pluginName}' configuration error: {$configIssue}", $code, $previous);
    }

    /**
     * Create a new plugin exception for dependency conflicts.
     *
     * @param string $pluginName Plugin name
     * @param string $dependencyIssue Dependency issue description
     * @param int $code Error code
     * @param Exception|null $previous Previous exception
     * @return static
     */
    public static function dependencyConflict(string $pluginName, string $dependencyIssue, int $code = 0, ?Exception $previous = null): self
    {
        return new self("Plugin '{$pluginName}' dependency conflict: {$dependencyIssue}", $code, $previous);
    }

    /**
     * Create a new plugin exception for file system errors.
     *
     * @param string $pluginName Plugin name
     * @param string $fileSystemIssue File system issue description
     * @param int $code Error code
     * @param Exception|null $previous Previous exception
     * @return static
     */
    public static function fileSystemError(string $pluginName, string $fileSystemIssue, int $code = 0, ?Exception $previous = null): self
    {
        return new self("Plugin '{$pluginName}' file system error: {$fileSystemIssue}", $code, $previous);
    }

    /**
     * Create a new plugin exception for plugin not found errors.
     *
     * @param string $pluginName Plugin name
     * @param int $code Error code
     * @param Exception|null $previous Previous exception
     * @return static
     */
    public static function pluginNotFound(string $pluginName, int $code = 0, ?Exception $previous = null): self
    {
        return new self("Plugin '{$pluginName}' not found", $code, $previous);
    }

    /**
     * Create a new plugin exception for invalid plugin structure.
     *
     * @param string $pluginName Plugin name
     * @param string $structureIssue Structure issue description
     * @param int $code Error code
     * @param Exception|null $previous Previous exception
     * @return static
     */
    public static function invalidStructure(string $pluginName, string $structureIssue, int $code = 0, ?Exception $previous = null): self
    {
        return new self("Plugin '{$pluginName}' has invalid structure: {$structureIssue}", $code, $previous);
    }
}