<?php

declare(strict_types=1);

namespace Pollora\Logging\Infrastructure\Services;

use Pollora\Hook\Domain\Contracts\Action;
use Pollora\Hook\Domain\Contracts\Filter;
use Pollora\Logging\Application\Services\WordPressErrorLoggingService;
use Pollora\Logging\Domain\Contracts\WordPressErrorHookRegistrarInterface;

class WordPressErrorHookRegistrar implements WordPressErrorHookRegistrarInterface
{
    public function __construct(
        private readonly Action $action,
        private readonly Filter $filter
    ) {}

    public function registerErrorHandlers(): void
    {
        $this->action->add('doing_it_wrong_run', function (string $function, string $message, string $version): void {
            $this->resolveLoggingService()?->handleDoingItWrong($function, $message, $version);
        }, 10, 3);

        $this->action->add('deprecated_function_run', function (string $function, string $replacement, string $version): void {
            $this->resolveLoggingService()?->handleDeprecatedFunction($function, $replacement, $version);
        }, 10, 3);

        $this->action->add('deprecated_argument_run', function (string $function, string $message, string $version): void {
            $this->resolveLoggingService()?->handleDeprecatedArgument($function, $message, $version);
        }, 10, 3);

        $this->filter->add('doing_it_wrong_trigger_error', fn (): bool => $this->resolveLoggingService()?->disableTriggerError() ?? true, PHP_INT_MAX, 4);

        $this->filter->add('deprecated_function_trigger_error', fn (): bool => $this->resolveLoggingService()?->disableTriggerError() ?? true, PHP_INT_MAX, 4);

        $this->filter->add('deprecated_argument_trigger_error', fn (): bool => $this->resolveLoggingService()?->disableTriggerError() ?? true, PHP_INT_MAX, 4);
    }

    /**
     * Resolve the logging service from the current application container.
     *
     * Uses app() helper to always get the current container instance,
     * avoiding stale references when the container is rebuilt (e.g. during tests).
     * Returns null gracefully if the container or binding is unavailable.
     */
    private function resolveLoggingService(): ?WordPressErrorLoggingService
    {
        try {
            return resolve(WordPressErrorLoggingService::class);
        } catch (\Throwable) {
            return null;
        }
    }
}
