<?php

declare(strict_types=1);

namespace Pollora\Mail;

use Illuminate\Mail\Message;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use Pollora\Container\Domain\ServiceLocator;
use Pollora\Hook\Infrastructure\Services\Filter;

/**
 * Class Mailer
 *
 * Handles email sending functionality using Laravel's mail system.
 * Provides WordPress-compatible mail sending interface with support for attachments.
 */
class Mailer
{
    protected ServiceLocator $locator;

    protected Filter $filter;

    public function __construct(ServiceLocator $locator)
    {
        $this->locator = $locator;
        $this->filter = $locator->resolve(Filter::class);
    }

    /**
     * Send an email message.
     *
     * Applies WordPress filters and handles email sending through Laravel's mail system.
     * Wraps any potential errors to ensure graceful failure.
     *
     * @param  string|array  $to  The recipient email address(es)
     * @param  string  $subject  The email subject
     * @param  string  $message  The email message content (HTML supported)
     * @param  string|array  $headers  Optional. Additional headers
     * @param  array  $attachments  Optional. Files to attach to the email
     * @return SentMessage|null Returns SentMessage on success, null on failure
     */
    public function send(
        string|array $to,
        string $subject,
        string $message,
        string|array $headers = '',
        array $attachments = []
    ): ?SentMessage {
        $values = $this->filter->apply('wp_mail', [$to, $subject, $message, $headers, $attachments]);
        [$to, $subject, $message, $headers, $attachments] = $values;

        try {
            return Mail::html($message, function (Message $mail) use ($to, $subject, $attachments): void {
                $mail->to($to)
                    ->subject($subject);

                $this->addAttachments($mail, $attachments);
            });
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Add attachments to the email message.
     *
     * Processes and attaches files to the email message.
     * Handles both array and string input formats for attachments.
     *
     * @param  Message  $mail  The email message instance
     * @param  array|string  $attachments  List of file paths to attach
     */
    private function addAttachments(Message $mail, array|string $attachments): void
    {
        if (! is_array($attachments)) {
            $attachments = explode("\n", str_replace("\r\n", "\n", $attachments));
        }

        $attachments = array_filter($attachments);

        foreach ($attachments as $attachment) {
            $mail->attach($attachment);
        }
    }
}
