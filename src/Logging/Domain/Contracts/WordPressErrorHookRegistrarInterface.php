<?php

declare(strict_types=1);

namespace Pollora\Logging\Domain\Contracts;

interface WordPressErrorHookRegistrarInterface
{
    public function registerErrorHandlers(): void;
}
