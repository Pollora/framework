<?php

declare(strict_types=1);

namespace Pollora\Mail;

use Illuminate\Support\ServiceProvider;

/**
 * Class WordPressMailServiceProvider
 *
 * Service provider that integrates with WordPress' mail system using filters.
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
     * Registers WordPress filters to intercept mail sending if enabled in config.
     * Uses the 'pre_wp_mail' filter introduced in WordPress 5.7.0 to handle mail
     * sending through Laravel's mail system while maintaining WordPress compatibility.
     */
    public function boot(): void
    {
        // Check if mail handling is enabled in the config (defaults to true if not set)
        $enableMailHandling = $this->app['config']->get('wordpress.enable_mail_handling', true);

        // Only register filters if mail handling is enabled
        if (! $enableMailHandling) {
            return;
        }

        // Register the pre_wp_mail filter to intercept mail sending
        add_filter('pre_wp_mail', function ($null, $atts) {
            // Extract mail parameters from the attributes array
            $to = $atts['to'] ?? '';
            $subject = $atts['subject'] ?? '';
            $message = $atts['message'] ?? '';
            $headers = $atts['headers'] ?? '';
            $attachments = $atts['attachments'] ?? [];
            
            // Send mail using our custom mailer
            $result = $this->app->make('wp.mail')->send($to, $subject, $message, $headers, $attachments);
            
            // Return true if mail was sent successfully, false otherwise
            return $result !== null;
        }, 10, 2);
    }
}
