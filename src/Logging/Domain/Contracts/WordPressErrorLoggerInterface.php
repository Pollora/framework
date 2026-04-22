<?php

declare(strict_types=1);

namespace Pollora\Logging\Domain\Contracts;

use Pollora\Logging\Domain\Models\WordPressError;

interface WordPressErrorLoggerInterface
{
    public function logError(WordPressError $error): void;
}
