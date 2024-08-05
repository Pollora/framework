<?php

declare(strict_types=1);

namespace Pollen\Mail;

use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;
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
        $values = Filter::apply('wp_mail', compact('to', 'subject', 'message', 'headers', 'attachments'));

        extract($values);

        return Mail::raw($message, function (Message $mail) use ($to, $subject, $attachments) {
            $mail->to($to)->subject($subject);

            $this->addAttachments($mail, $attachments);
        });
    }

    private function addAttachments(Message $mail, array|string $attachments): void
    {
        if (!is_array($attachments)) {
            $attachments = explode("\n", str_replace("\r\n", "\n", $attachments));
        }

        $attachments = array_filter($attachments);

        foreach ($attachments as $attachment) {
            $mail->attach($attachment);
        }
    }
}
