<?php

namespace Pollora\Logging\Domain\Contracts;

interface WordPressErrorHookRegistrarInterface
{
    public function registerErrorHandlers(): void;
}