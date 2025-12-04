<?php

declare(strict_types=1);

namespace Pollora\Logging\Infrastructure\Adapters;

use Illuminate\Log\LogManager;
use Pollora\Logging\Domain\Contracts\WordPressErrorLoggerInterface;
use Pollora\Logging\Domain\Models\WordPressError;
use Psr\Log\LoggerInterface;

class LaravelWordPressErrorLogger implements WordPressErrorLoggerInterface
{
    private readonly LoggerInterface $logger;

    public function __construct(LogManager $logManager)
    {
        $this->logger = $logManager->channel('wordpress');
    }

    public function logError(WordPressError $error): void
    {
        $level = $error->getLogLevel();
        $message = $error->getLogMessage();
        $context = $error->getLogContext();

        $this->logger->log($level, $message, $context);
    }
}
