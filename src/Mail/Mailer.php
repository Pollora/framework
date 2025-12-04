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
 * Handles email sending functionality using Laravel's mail system with WordPress compatibility.
 *
 * This class provides a WordPress-compatible mail sending interface that integrates seamlessly
 * with Laravel's mail system. It supports all standard WordPress mail features including
 * custom headers, attachments, and filter hooks while leveraging Laravel's robust email
 * infrastructure underneath.
 *
 * Key features:
 * - WordPress wp_mail filter compatibility
 * - Support for complex email headers (CC, BCC, From, Reply-To)
 * - File attachment handling
 * - Graceful error handling
 * - Email address parsing and validation
 *
 * @author Pollora Team
 *
 * @since 1.0.0
 */
class Mailer
{
    /**
     * Service locator instance for dependency resolution.
     */
    protected ServiceLocator $locator;

    /**
     * Filter service for applying WordPress-style hooks and filters.
     */
    protected Filter $filter;

    /**
     * Constructor - Initialize the mailer with required dependencies.
     *
     * @param  Application  $app  The Laravel application instance for dependency injection
     */
    public function __construct(Application $app)
    {
        $this->filter = $app->get(Filter::class);
    }

    /**
     * Send an email message with WordPress filter compatibility.
     *
     * This method serves as the main entry point for sending emails. It applies the WordPress
     * 'wp_mail' filter to allow plugins and themes to modify email data before sending.
     * The method handles various email formats, headers, and attachments while providing
     * graceful error handling.
     *
     * @param  string|array  $to  The recipient email address(es). Can be a single email string
     *                            or an array of email addresses.
     * @param  string  $subject  The email subject line
     * @param  string  $message  The email message content. HTML is supported and recommended.
     * @param  string|array  $headers  Optional. Additional email headers as string or array.
     *                                 Supports standard headers like CC, BCC, From, Reply-To.
     * @param  array  $attachments  Optional. Array of file paths to attach to the email.
     *                              Files must be accessible by the application.
     * @return SentMessage|null Returns SentMessage instance on successful send, null on failure.
     *
     * @throws \InvalidArgumentException When required parameters are invalid
     *
     * @example
     * ```php
     * $mailer = new Mailer($app);
     *
     * // Simple email
     * $result = $mailer->send('user@example.com', 'Subject', 'Message content');
     *
     * // Complex email with headers and attachments
     * $result = $mailer->send(
     *     ['user1@example.com', 'user2@example.com'],
     *     'Important Update',
     *     '<h1>HTML Content</h1><p>Your message here</p>',
     *     [
     *         'From: sender@example.com',
     *         'CC: cc@example.com',
     *         'Reply-To: reply@example.com'
     *     ],
     *     ['/path/to/file.pdf', '/path/to/image.jpg']
     * );
     * ```
     */
    public function send(
        string|array $to,
        string $subject,
        string $message,
        string|array $headers = '',
        array $attachments = []
    ): ?SentMessage {
        // Apply the wp_mail filter to maintain WordPress plugin compatibility
        // This allows other plugins to modify mail data before sending
        $mailData = $this->filter->apply('wp_mail', compact('to', 'subject', 'message', 'headers', 'attachments'));

        // Extract filtered values with fallbacks to original values
        $to = $mailData['to'] ?? $to;
        $subject = $mailData['subject'] ?? $subject;
        $message = $mailData['message'] ?? $message;
        $headers = $mailData['headers'] ?? $headers;
        $attachments = $mailData['attachments'] ?? $attachments;

        try {
            return Mail::html($message, function (Message $mail) use ($to, $subject, $headers, $attachments): void {
                // Configure the email message
                $mail->to($this->cleanEmailAddresses($to))
                    ->subject($subject);

                // Apply headers if provided
                $this->processHeaders($mail, $headers);

                // Attach files if any
                $this->addAttachments($mail, $attachments);
            });
        } catch (\Throwable) {
            // Return null on any error to maintain WordPress wp_mail compatibility
            // WordPress wp_mail returns false on failure, we return null for type safety
            return null;
        }
    }

    /**
     * Process and apply email headers to the message.
     *
     * This method handles the parsing and application of various email headers.
     * It supports both string and array formats for headers and handles special
     * cases like CC, BCC, From, and Reply-To headers appropriately.
     *
     * Supported header formats:
     * - String with newline-separated headers: "From: sender@example.com\nCC: cc@example.com"
     * - Array of header strings: ['From: sender@example.com', 'CC: cc@example.com']
     *
     * Special headers handled:
     * - CC: Carbon copy recipients
     * - BCC: Blind carbon copy recipients
     * - From: Sender address (with optional name)
     * - Reply-To: Reply address
     * - Content-Type: Skipped (managed by Laravel)
     *
     * @param  Message  $mail  The Laravel mail message instance to modify
     * @param  string|array  $headers  Headers to process and apply
     *
     * @internal This method is used internally by the send() method
     */
    private function processHeaders(Message $mail, string|array $headers): void
    {
        if (empty($headers)) {
            return;
        }

        // Normalize headers to array format for consistent processing
        $headerArray = is_array($headers)
            ? $headers
            : array_filter(preg_split('/\r?\n/', $headers));

        // Process each header individually
        foreach ($headerArray as $header) {
            $header = trim($header);

            // Skip empty headers or headers without colon separator
            if (empty($header) || ! str_contains($header, ':')) {
                continue;
            }

            // Split header name and value (limit to 2 parts in case value contains colons)
            [$name, $value] = array_map('trim', explode(':', $header, 2));

            // Apply the header using the appropriate method
            $this->applyHeader($mail, strtolower($name), $value);
        }
    }

    /**
     * Apply a specific header to the email message.
     *
     * This method uses pattern matching to handle different header types appropriately.
     * Special headers like CC, BCC, From, and Reply-To use dedicated Laravel methods,
     * while other headers are added as raw text headers.
     *
     * @param  Message  $mail  The mail message instance to modify
     * @param  string  $name  The header name (normalized to lowercase)
     * @param  string  $value  The header value
     *
     * @internal This method is used internally by processHeaders()
     */
    private function applyHeader(Message $mail, string $name, string $value): void
    {
        match ($name) {
            'cc' => $mail->cc($this->parseEmailAddresses($value)),
            'bcc' => $mail->bcc($this->parseEmailAddresses($value)),
            'from' => $this->setFromHeader($mail, $value),
            'reply-to' => $mail->replyTo($this->parseEmailAddresses($value)),
            'content-type' => null, // Skip - managed by Laravel Mail system
            default => $mail->getHeaders()->addTextHeader($name, $value)
        };
    }

    /**
     * Set the From header with proper name and address handling.
     *
     * This method handles the From header specially because it may contain
     * both a name and email address, which need to be set using Laravel's
     * dedicated from() method rather than as a raw header.
     *
     * @param  Message  $mail  The mail message instance to modify
     * @param  string  $value  The from header value (e.g., "John Doe <john@example.com>")
     *
     * @internal This method is used internally by applyHeader()
     */
    private function setFromHeader(Message $mail, string $value): void
    {
        $parsed = $this->parseEmailAddress($value);

        if (is_array($parsed)) {
            $mail->from($parsed['address'], $parsed['name'] ?? null);
        } else {
            $mail->from($parsed);
        }
    }

    /**
     * Add file attachments to the email message.
     *
     * This method processes attachments in various formats (array or string)
     * and adds them to the email. It handles both absolute and relative file paths
     * and filters out empty attachment entries.
     *
     * @param  Message  $mail  The mail message instance to modify
     * @param  array|string  $attachments  File paths to attach. Can be:
     *                                     - Array of file paths: ['/path/file1.pdf', '/path/file2.jpg']
     *                                     - String with newline-separated paths: "/path/file1.pdf\n/path/file2.jpg"
     *
     * @throws \InvalidArgumentException When attachment files don't exist or aren't readable
     *
     * @internal This method is used internally by the send() method
     */
    private function addAttachments(Message $mail, array|string $attachments): void
    {
        // Normalize attachments to array format
        if (! is_array($attachments)) {
            $attachments = explode("\n", str_replace("\r\n", "\n", $attachments));
        }

        // Filter out empty entries and attach each file
        $attachments = array_filter(array_map('trim', $attachments));

        foreach ($attachments as $attachment) {
            if (! empty($attachment)) {
                $mail->attach($attachment);
            }
        }
    }

    /**
     * Clean and normalize email addresses for consistency.
     *
     * This method removes extraneous quotes and whitespace from email addresses
     * while preserving the proper format for "Name <email@example.com>" style addresses.
     * It handles both single email addresses and arrays of addresses.
     *
     * @param  string|array  $emails  Email address(es) to clean and normalize
     * @return string|array Cleaned email address(es) in the same format as input
     *
     * @example
     * ```php
     * $clean = $this->cleanEmailAddresses('"John Doe" <john@example.com>');
     * // Returns: "John Doe <john@example.com>"
     *
     * $clean = $this->cleanEmailAddresses(['  user1@example.com  ', '"User 2" <user2@example.com>']);
     * // Returns: ['user1@example.com', 'User 2 <user2@example.com>']
     * ```
     *
     * @internal This method is used internally by the send() method
     */
    private function cleanEmailAddresses(string|array $emails): string|array
    {
        if (is_array($emails)) {
            return array_map(fn ($email) => $this->cleanEmailAddress($email), $emails);
        }

        return $this->cleanEmailAddress($emails);
    }

    /**
     * Clean and normalize a single email address.
     *
     * This method handles various email address formats and normalizes them
     * to a consistent format. It removes extra quotes and whitespace while
     * preserving name information when present.
     *
     * Supported formats:
     * - Simple: user@example.com
     * - With name: John Doe <user@example.com>
     * - With quoted name: "John Doe" <user@example.com>
     * - With extra whitespace: "  John Doe  " <  user@example.com  >
     *
     * @param  string  $email  The email address to clean
     * @return string The cleaned and normalized email address
     *
     * @internal This method is used internally by cleanEmailAddresses()
     */
    private function cleanEmailAddress(string $email): string
    {
        // Remove extra quotes and trim whitespace
        $email = trim($email, " \"'");

        // Match both quoted and unquoted names
        if (preg_match('/^(?:"?([^"]*?)"?\s*)?<([^>]+)>$/', $email, $matches)) {
            $name = trim($matches[1] ?? '');
            $addr = trim($matches[2] ?? '');

            return $name !== ''
                ? $name.' <'.$addr.'>'
                : $addr;
        }

        // Return simple email address as-is
        return $email;
    }

    /**
     * Parse a single email address from a header value.
     *
     * This method extracts email address and optional name information from
     * various email address formats commonly found in email headers.
     *
     * @param  string  $address  The email address string to parse
     * @return string|array Returns:
     *                      - string: Simple email address when no name is present
     *                      - array: ['address' => 'email', 'name' => 'name'] when name is present
     *
     * @example
     * ```php
     * $parsed = $this->parseEmailAddress('john@example.com');
     * // Returns: 'john@example.com'
     *
     * $parsed = $this->parseEmailAddress('John Doe <john@example.com>');
     * // Returns: ['address' => 'john@example.com', 'name' => 'John Doe']
     *
     * $parsed = $this->parseEmailAddress('"John Doe" <john@example.com>');
     * // Returns: ['address' => 'john@example.com', 'name' => 'John Doe']
     * ```
     *
     * @internal This method is used internally for header processing
     */
    private function parseEmailAddress(string $address): string|array
    {
        $address = trim($address, " \"'");

        // Return directly if no match for "Name <email>"
        if (preg_match('/^(?:"?([^"]*)"?\s*)?<([^>]+)>$/', $address, $matches) !== 1) {
            return $address;
        }

        $name = trim($matches[1] ?? '');
        $email = trim($matches[2] ?? '');

        // Return array only when a name is present
        return $name !== ''
            ? ['name' => $name, 'address' => $email]
            : $email;
    }

    /**
     * Parse multiple email addresses from a comma-separated string.
     *
     * This method handles header values that contain multiple email addresses
     * separated by commas. Each address is individually parsed and can contain
     * name information.
     *
     * @param  string  $addresses  Comma-separated email addresses string
     * @return array Array of parsed email addresses. Each element is either:
     *               - string: Simple email address
     *               - array: ['address' => 'email', 'name' => 'name'] for addresses with names
     *
     * @example
     * ```php
     * $parsed = $this->parseEmailAddresses('john@example.com, Jane Doe <jane@example.com>');
     * // Returns: [
     * //     'john@example.com',
     * //     ['address' => 'jane@example.com', 'name' => 'Jane Doe']
     * // ]
     * ```
     *
     * @internal This method is used internally for processing CC, BCC, and Reply-To headers
     */
    private function parseEmailAddresses(string $addresses): array
    {
        return array_filter(
            array_map(
                fn ($addr) => $this->parseEmailAddress(trim($addr)),
                explode(',', $addresses)
            ),
            fn ($addr) => ! empty($addr) // Remove empty entries
        );
    }
}
