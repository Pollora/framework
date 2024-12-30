<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Installation;

/**
 * Exception thrown when WordPress installation fails.
 *
 * This exception is used to handle WordPress-specific installation errors
 * and provides conversion from WordPress WP_Error objects.
 *
 * @extends \RuntimeException
 */
class WordPressInstallationException extends \RuntimeException
{
    /**
     * Create exception from WordPress error object.
     *
     * @param \WP_Error $error The WordPress error object
     * @return self
     */
    public static function fromWpError(\WP_Error $error): self
    {
        return new self($error->get_error_message());
    }
}
