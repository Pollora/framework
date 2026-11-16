<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Mail\Message;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Mail;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Mail\Mailer;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Runs Mailer::send() against a fake Mail facade that hands the Message to the callback, then returns it.
 */
beforeEach(function (): void {
    $this->filter = Mockery::mock(Filter::class);
    $this->filter->shouldReceive('apply')->andReturnUsing(fn (string $hook, array $data): array => $data)->byDefault();

    $app = Mockery::mock(Application::class)->makePartial();
    $app->shouldReceive('get')->with(Filter::class)->andReturn($this->filter);
    $this->mailer = new Mailer($app);

    $this->email = new Email;
    $this->sentHtml = null;
    Mail::swap($fake = Mockery::mock('mailer'));
    $fake->shouldReceive('html')->andReturnUsing(function (string $html, Closure $callback): SentMessage {
        $this->sentHtml = $html;
        $this->email->html($html);
        $callback(new Message($this->email));

        return new SentMessage(new Symfony\Component\Mailer\SentMessage((new Email)->from('from@example.test')->to('to@example.test')->text('x'), new Envelope(new Address('from@example.test'), [new Address('to@example.test')])));
    });
});

afterEach(function (): void {
    Facade::clearResolvedInstances();
    Mockery::close();
});

describe('Mailer::send', function (): void {
    it('sends the HTML body to the recipient, with the subject', function (): void {
        $sent = $this->mailer->send('jane@example.test', 'Welcome', '<p>Hi</p>');

        expect($sent)->toBeInstanceOf(SentMessage::class)
            ->and($this->sentHtml)->toBe('<p>Hi</p>')
            ->and($this->email->getSubject())->toBe('Welcome')
            ->and($this->email->getTo()[0]->getAddress())->toBe('jane@example.test');
    });

    it('lets a wp_mail filter change what is sent', function (): void {
        $this->filter->shouldReceive('apply')->with('wp_mail', Mockery::type('array'))->andReturn([
            'to' => 'other@example.test', 'subject' => 'Changed', 'message' => 'New body', 'headers' => '', 'attachments' => [],
        ]);

        $this->mailer->send('jane@example.test', 'Welcome', 'Old body');

        expect($this->sentHtml)->toBe('New body')
            ->and($this->email->getSubject())->toBe('Changed')
            ->and($this->email->getTo()[0]->getAddress())->toBe('other@example.test');
    });

    it('applies the cc, bcc, from and reply-to headers and keeps any other as a text header', function (): void {
        $this->mailer->send(
            ['one@example.test', 'two@example.test'],
            'Subject',
            'Body',
            "From: Shop <shop@example.test>\r\nCC: cc@example.test\r\nBcc: bcc@example.test\r\nReply-To: reply@example.test\r\nContent-Type: text/html\r\nX-Custom: yes\r\nnot a header",
        );

        expect($this->email->getFrom()[0]->getAddress())->toBe('shop@example.test')
            ->and($this->email->getFrom()[0]->getName())->toBe('Shop')
            ->and($this->email->getCc()[0]->getAddress())->toBe('cc@example.test')
            ->and($this->email->getBcc()[0]->getAddress())->toBe('bcc@example.test')
            ->and($this->email->getReplyTo()[0]->getAddress())->toBe('reply@example.test')
            ->and($this->email->getHeaders()->get('x-custom')->getBodyAsString())->toBe('yes')
            ->and(array_map(fn ($a) => $a->getAddress(), $this->email->getTo()))->toBe(['one@example.test', 'two@example.test']);
    });

    it('accepts the headers as an array and a bare from address', function (): void {
        $this->mailer->send('a@example.test', 'S', 'B', ['From: bare@example.test', '', '0']);

        expect($this->email->getFrom()[0]->getAddress())->toBe('bare@example.test');
    });

    it('attaches the files, from an array or from a newline separated string', function (): void {
        $file = tempnam(sys_get_temp_dir(), 'mailer');
        file_put_contents($file, 'attached');

        try {
            $this->mailer->send('a@example.test', 'S', 'B', '', [$file, '  ']);
            expect($this->email->getAttachments())->toHaveCount(1);
        } finally {
            unlink($file);
        }
    });

    it('answers null instead of throwing when the mail cannot be sent', function (): void {
        Mail::swap($broken = Mockery::mock('mailer'));
        $broken->shouldReceive('html')->andThrow(new RuntimeException('smtp down'));

        expect($this->mailer->send('a@example.test', 'S', 'B'))->toBeNull();
    });
});
