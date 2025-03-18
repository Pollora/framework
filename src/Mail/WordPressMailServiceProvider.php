<?php

declare(strict_types=1);

namespace Pollora\Mail;

use Illuminate\Support\ServiceProvider;

/**
 * Class WordPressMailServiceProvider
 *
 * Service provider that overrides WordPress' wp_mail function to use the Laravel mailer.
 * Provides integration between WordPress mailing system and Laravel's mail functionality.
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
        $this->app->singleton('wp.mail', fn ($app): \Pollora\Mail\Mailer => new Mailer);
    }

    /**
     * Bootstrap any application services.
     *
     * Defines the wp_mail function if it doesn't exist, providing Laravel-powered
     * mail functionality while maintaining WordPress compatibility.
     */
    public function boot(): void
    {
        if (! function_exists('wp_mail')) {
            $this->app->bind('wp_mail', fn ($app): \Closure => static function ($to, $subject, $message, $headers = '', $attachments = []) use ($app): bool {
                $result = $app->make(Mailer::class)->send($to, $subject, $message, $headers, $attachments);

                return $result !== null;
            });
        }
    }
}
