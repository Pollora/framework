<?php

declare(strict_types=1);

namespace Pollen\Hashing;

use Illuminate\Support\ServiceProvider;

/**
 * Provide 'wp.hash' service to allow for hashing using WordPress'
 * hashing methods.
 *
 * @author Jordan Doyle <jordan@doyle.wf>
 */
class HashServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('wp.hash', function () {
            return new WordPressHasher();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['wp.hash'];
    }
}
