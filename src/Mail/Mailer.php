<?php

declare(strict_types=1);

namespace Pollen\Mail;

use Illuminate\Mail\Message;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use Pollen\Support\Facades\Filter;

class Mailer
{
    public function send(
        string|array $to,
        string $subject,
        string $message,
        string|array $headers = '',
        array $attachments = []
    ): ?SentMessage {
        $values = Filter::apply('wp_mail', [$to, $subject, $message, $headers, $attachments]);
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
