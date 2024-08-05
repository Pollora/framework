<?php

declare(strict_types=1);

namespace Pollen\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Illuminate\Mail\SentMessage;

/**
 * @method static ?SentMessage send(string|array $to, string $subject, string $message, string|array $headers = '', array $attachments = [])
 *
 * @see \Pollen\Mail\Mailer
 */
class Mail extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'wp.mail';
    }
}
