<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Installation;

class WordPressInstallationException extends \RuntimeException
{
    public static function fromWpError(\WP_Error $error): self
    {
        return new self($error->get_error_message());
    }
}
