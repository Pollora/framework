<?php

declare(strict_types=1);

namespace Pollora\Hashing;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Provide 'wp.hash' service to allow for hashing using WordPress'
 * hashing methods.
 */
class HashServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('wp.hash', fn ($app): WordPressHasher => new WordPressHasher);
        $this->app->alias('wp.hash', WordPressHasher::class);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return ['wp.hash', WordPressHasher::class];
    }
}
