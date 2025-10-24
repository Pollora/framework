<?php

namespace Pollora\Logging\Domain\Contracts;

use Pollora\Logging\Domain\Models\WordPressError;

interface WordPressErrorLoggerInterface
{
    public function logError(WordPressError $error): void;
}