<?php

declare(strict_types=1);

namespace Pollora\Logging\Infrastructure\Services;

use Illuminate\Contracts\Container\Container;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\Logging\Application\Services\WordPressErrorLoggingService;
use Pollora\Logging\Domain\Contracts\WordPressErrorHookRegistrarInterface;

class WordPressErrorHookRegistrar implements WordPressErrorHookRegistrarInterface
{
    public function __construct(
        private readonly Container $container,
        private readonly Action $action,
        private readonly Filter $filter
    ) {}

    public function registerErrorHandlers(): void
    {
        $this->action->add('doing_it_wrong_run', function (string $function, string $message, string $version) {
            $this->container->make(WordPressErrorLoggingService::class)->handleDoingItWrong($function, $message, $version);
        }, 10, 3);

        $this->action->add('deprecated_function_run', function (string $function, string $replacement, string $version) {
            $this->container->make(WordPressErrorLoggingService::class)->handleDeprecatedFunction($function, $replacement, $version);
        }, 10, 3);

        $this->action->add('deprecated_argument_run', function (string $function, string $message, string $version) {
            $this->container->make(WordPressErrorLoggingService::class)->handleDeprecatedArgument($function, $message, $version);
        }, 10, 3);

        $this->filter->add('doing_it_wrong_trigger_error', function () {
            return $this->container->make(WordPressErrorLoggingService::class)->disableTriggerError();
        }, PHP_INT_MAX, 4);

        $this->filter->add('deprecated_function_trigger_error', function () {
            return $this->container->make(WordPressErrorLoggingService::class)->disableTriggerError();
        }, PHP_INT_MAX, 4);

        $this->filter->add('deprecated_argument_trigger_error', function () {
            return $this->container->make(WordPressErrorLoggingService::class)->disableTriggerError();
        }, PHP_INT_MAX, 4);
    }
}
