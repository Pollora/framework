<?php

declare(strict_types=1);

namespace Pollora\Mail;

use Illuminate\Support\ServiceProvider;

/**
 * Override WordPress' wp_mail function to use the Laravel mailer.
 */
class WordPressMailServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('wp.mail', fn($app): \Pollora\Mail\Mailer => new Mailer);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! function_exists('wp_mail')) {
            $this->app->bind('wp_mail', fn($app): \Closure => function ($to, $subject, $message, $headers = '', $attachments = []) use ($app): bool {
                $result = $app->make(Mailer::class)->send($to, $subject, $message, $headers, $attachments);

                return $result !== null;
            });
        }
    }
}
