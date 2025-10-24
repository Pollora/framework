<?php

namespace Pollora\Logging\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Logging\Application\Services\WordPressErrorLoggingService;
use Pollora\Logging\Domain\Contracts\WordPressErrorLoggerInterface;
use Pollora\Logging\Domain\Contracts\WordPressErrorHookRegistrarInterface;
use Pollora\Logging\Domain\Services\WordPressErrorHandler;
use Pollora\Logging\Infrastructure\Adapters\LaravelWordPressErrorLogger;
use Pollora\Logging\Infrastructure\Services\WordPressErrorHookRegistrar;

class LoggingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerLoggingChannels();
        $this->registerServices();
    }

    public function boot(): void
    {
        $hookRegistrar = $this->app->make(WordPressErrorHookRegistrarInterface::class);
        $hookRegistrar->registerErrorHandlers();
    }

    private function registerLoggingChannels(): void
    {
        $existingChannels = config('logging.channels', []);
        $polloraChannels = $this->getDefaultLoggingChannels();
        $mergedChannels = array_merge($polloraChannels, $existingChannels);
        config(['logging.channels' => $mergedChannels]);
    }

    private function registerServices(): void
    {
        $this->app->bind(WordPressErrorLoggerInterface::class, LaravelWordPressErrorLogger::class);
        $this->app->bind(WordPressErrorHookRegistrarInterface::class, WordPressErrorHookRegistrar::class);
        
        $this->app->singleton(WordPressErrorHandler::class);
        $this->app->singleton(WordPressErrorLoggingService::class);
    }

    protected function getDefaultLoggingChannels(): array
    {
        return [
            'wordpress' => [
                'driver' => 'single',
                'path' => storage_path('logs/wordpress.log'),
                'level' => env('WORDPRESS_LOG_LEVEL', 'debug'),
                'days' => env('WORDPRESS_LOG_DAYS', 7),
                'replace_placeholders' => true,
            ],
        ];
    }
}