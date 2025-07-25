<?php

declare(strict_types=1);

namespace Pollora\Mail;

use Illuminate\Foundation\Application;
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
 *
 * This class is used by the WordPress mail filter system when mail handling is enabled.
 */
class Mailer
{
    protected ServiceLocator $locator;

    protected Filter $filter;

    public function __construct(Application $app)
    {
        $this->filter = $app->get(Filter::class);
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
        // Apply the wp_mail filter to allow other plugins to modify the mail data
        // This maintains compatibility with WordPress plugins that hook into wp_mail
        $filtered = $this->filter->apply('wp_mail', compact('to', 'subject', 'message', 'headers', 'attachments'));

        // Extract the filtered values
        $to = $filtered['to'] ?? $to;
        $subject = $filtered['subject'] ?? $subject;
        $message = $filtered['message'] ?? $message;
        $headers = $filtered['headers'] ?? $headers;
        $attachments = $filtered['attachments'] ?? $attachments;

        try {
            return Mail::html($message, function (Message $mail) use ($to, $subject, $headers, $attachments): void {
                $mail->to($to)
                    ->subject($subject);

                // Process headers if provided
                $this->processHeaders($mail, $headers);

                // Add attachments if any
                $this->addAttachments($mail, $attachments);
            });
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Process email headers and apply them to the message.
     *
     * @param  Message  $mail  The email message instance
     * @param  string|array  $headers  Headers to process
     */
    private function processHeaders(Message $mail, string|array $headers): void
    {
        if (empty($headers)) {
            return;
        }

        // Convert string headers to array
        if (! is_array($headers)) {
            $headers = explode("\n", str_replace(["\r\n", "\r"], "\n", $headers));
        }

        foreach ($headers as $header) {
            $header = trim($header);
            if (empty($header)) {
                continue;
            }

            // Process common email headers
            if (preg_match('/^CC: (.+)$/i', $header, $matches)) {
                $mail->cc($matches[1]);
            } elseif (preg_match('/^BCC: (.+)$/i', $header, $matches)) {
                $mail->bcc($matches[1]);
            } elseif (preg_match('/^From: (.+)$/i', $header, $matches)) {
                $mail->from($matches[1]);
            } elseif (preg_match('/^Reply-To: (.+)$/i', $header, $matches)) {
                $mail->replyTo($matches[1]);
            }
            // Other headers could be added as needed
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
