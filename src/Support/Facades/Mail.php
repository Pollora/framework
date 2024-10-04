<?php

declare(strict_types=1);

namespace Pollen\Support\Facades;

use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ?SentMessage send(string|array $to, string $subject, string $message, string|array $headers = '', array $attachments = [])
 *
 * @see \Pollen\Mail\Mailer
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
