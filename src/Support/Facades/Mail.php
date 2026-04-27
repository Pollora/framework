<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Facade;
use Pollora\Mail\Mailer;

/**
 * Facade for WordPress Mail functionality.
 *
 * Provides an interface to WordPress mailing system with Laravel-style syntax
 * and improved type safety.
 *
 * @method static SentMessage|null send(string|array $to, string $subject, string $message, string|array $headers = '', array $attachments = []) Send an email
 *
 * @see Mailer
 */
class Mail extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wp.mail';
    }
}
