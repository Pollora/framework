<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when the theme layer is used before it is set up, or a theme cannot be wired.
 */
final class ThemeSetupException extends RuntimeException
{
    public static function serviceNotRegistered(): self
    {
        return new self('Unable to resolve ThemeService. Make sure it is properly registered: the theme service provider must run before a theme is loaded.');
    }

    public static function nameRequired(): self
    {
        return new self("Theme name is required for self-registered themes. Pass the theme's directory name.");
    }

    public static function collectionFactoryMissing(): self
    {
        return new self('CollectionFactory not initialized. Call ThemeCollection::setFactory() before using this class.');
    }

    public static function configRepositoryMissing(): self
    {
        return new self('ConfigRepository not initialized. Call ThemeConfig::setRepository() before using this class.');
    }

    /**
     * @param  string  $component  The component class that failed to register
     * @param  Throwable  $previous  Why it failed
     */
    public static function componentFailed(string $component, Throwable $previous): self
    {
        return new self(sprintf('Failed to register component %s: ', $component).$previous->getMessage(), 0, $previous);
    }

    /**
     * @param  string  $template  The stub that was asked for
     */
    public static function stubNotBundled(string $template): self
    {
        return new self(sprintf('The stub template "%s" is not bundled with the framework. Check the name; the stubs live in the framework\'s stubs folder.', $template));
    }
}
