<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Exceptions;

use Exception;

/**
 * Custom exception class for theme-related errors.
 *
 * Provides additional context information for theme processing errors
 * to help with debugging and error handling.
 */
class ThemeException extends Exception {}
