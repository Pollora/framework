<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for WordPress Mail functionality.
 *
 * Provides an interface to WordPress mailing system with Laravel-style syntax
 * and improved type safety.
 *
 * @method static ?SentMessage send(string|array $to, string $subject, string $message, string|array $headers = '', array $attachments = []) Send an email
 *
 * @see \Pollora\Mail\Mailer
 */
class Mail extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wp.mail';
    }
}
