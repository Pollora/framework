<?php

namespace Pollen\Mail;

use Illuminate\Support\ServiceProvider;

/**
 * Override WordPress' wp_mail function to use the Laravel mailer.
 *
 * @author Jordan Doyle <jordan@doyle.wf>
 */
class WordPressMailServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function register()
    {
        include_once 'Mailer.php';
    }
}
