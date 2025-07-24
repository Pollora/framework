<?php

declare(strict_types=1);

namespace Pollora\Mail;

use Illuminate\Support\ServiceProvider;

/**
 * Class WordPressMailServiceProvider
 *
 * Service provider that overrides WordPress' wp_mail function to use the Laravel mailer.
 * Provides integration between WordPress mailing system and Laravel's mail functionality.
 * This can be enabled or disabled via the 'enable_mail_handling' option in the wordpress config.
 */
class WordPressMailServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Binds the Mailer instance as a singleton in the application container.
     */
    public function register(): void
    {
        $this->app->singleton('wp.mail', fn ($app): Mailer => new Mailer($app));
    }

    /**
     * Bootstrap any application services.
     *
     * Defines the wp_mail function if it doesn't exist, providing Laravel-powered
     * mail functionality while maintaining WordPress compatibility.
     *
     * This will only override the wp_mail function if:
     * 1. The function doesn't already exist
     * 2. The 'enable_mail_handling' option is set to true in the wordpress config
     */
    public function boot(): void
    {
        // Check if mail handling is enabled in the config (defaults to true if not set)
        $enableMailHandling = $this->app['config']->get('wordpress.enable_mail_handling', true);

        // Only override wp_mail if it doesn't exist and mail handling is enabled
        if (!$enableMailHandling || function_exists('wp_mail')) {
            return;
        }

        $this->app->bind('wp_mail', fn ($app): \Closure => static function ($to, $subject, $message, $headers = '', $attachments = []) use ($app): bool {
            $result = $app->make(Mailer::class)->send($to, $subject, $message, $headers, $attachments);

            return $result !== null;
        });
    }
}
