<?php

namespace Pollora\Logging\Application\Services;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Pollora\Logging\Domain\Services\WordPressErrorHandler;

class WordPressErrorLoggingService
{
    public function __construct(
        private readonly WordPressErrorHandler $errorHandler,
        private readonly Application $app,
        private readonly Request $request
    ) {}

    public function handleDoingItWrong(string $function, string $message, string $version): void
    {
        $context = $this->buildContext();
        $this->errorHandler->handleDoingItWrong($function, $message, $version, $context);
    }

    public function handleDeprecatedFunction(string $function, string $replacement, string $version): void
    {
        $context = $this->buildContext();
        $this->errorHandler->handleDeprecatedFunction($function, $replacement, $version, $context);
    }

    public function handleDeprecatedArgument(string $function, string $message, string $version): void
    {
        $context = $this->buildContext();
        $this->errorHandler->handleDeprecatedArgument($function, $message, $version, $context);
    }

    public function disableTriggerError(): bool
    {
        return $this->errorHandler->disableTriggerError();
    }

    private function buildContext(): array
    {
        $context = [
            'url' => $this->request->fullUrl(),
            'method' => $this->request->method(),
            'ip' => $this->request->ip(),
        ];

        if ($this->app->environment('local')) {
            $context['backtrace'] = $this->getCleanBacktrace();
        }

        return $context;
    }

    private function getCleanBacktrace(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $cleanTrace = [];

        foreach ($trace as $index => $item) {
            if (isset($item['class']) && str_contains($item['class'], 'WordPressError')) {
                continue;
            }

            $cleanTrace[] = sprintf(
                '#%d %s%s%s() in %s:%d',
                $index,
                $item['class'] ?? '',
                isset($item['class']) ? $item['type'] : '',
                $item['function'] ?? 'unknown',
                $item['file'] ?? 'unknown',
                $item['line'] ?? 0
            );
        }

        return $cleanTrace;
    }
}