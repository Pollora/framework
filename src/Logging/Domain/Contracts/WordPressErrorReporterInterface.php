<?php

declare(strict_types=1);

namespace Pollora\Logging\Domain\Contracts;

/**
 * Contract of the service that reports WordPress errors, fronted by the WordPressError facade.
 */
interface WordPressErrorReporterInterface
{
    public function handleDoingItWrong(string $function, string $message, string $version): void;

    public function handleDeprecatedFunction(string $function, string $replacement, string $version): void;

    public function handleDeprecatedArgument(string $function, string $message, string $version): void;

    public function disableTriggerError(): bool;
}
