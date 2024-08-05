<?php

declare(strict_types=1);

namespace Pollen\Mail;

use Illuminate\Support\ServiceProvider;

/**
 * Override WordPress' wp_mail function to use the Laravel mailer.
 */
class WordPressMailServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton('wp.mail', function ($app) {
            return new Mailer();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        if (!function_exists('wp_mail')) {
            $this->app->bind('wp_mail', function ($app) {
                return function ($to, $subject, $message, $headers = '', $attachments = []) use ($app) {
                    $result = $app->make(Mailer::class)->send($to, $subject, $message, $headers, $attachments);
                    return $result !== null;
                };
            });
        }
    }
}
