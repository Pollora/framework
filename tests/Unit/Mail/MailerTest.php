<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Mail\Message;
use Pollora\Hook\Domain\Contracts\Filter;
use Pollora\Mail\Mailer;

beforeEach(function (): void {
    $app = Mockery::mock(Application::class)->makePartial();
    $filter = Mockery::mock(Filter::class);
    $app->shouldReceive('get')->with(Filter::class)->andReturn($filter);

    $this->mailer = new Mailer($app);
    $this->reflection = new ReflectionClass($this->mailer);
});

describe('Mailer email parsing', function (): void {
    it('parses simple email address', function (): void {
        $method = $this->reflection->getMethod('parseEmailAddress');
        $method->setAccessible(true);

        expect($method->invoke($this->mailer, 'user@example.com'))->toBe('user@example.com');
    });

    it('parses email with name', function (): void {
        $method = $this->reflection->getMethod('parseEmailAddress');
        $method->setAccessible(true);

        $result = $method->invoke($this->mailer, 'John Doe <john@example.com>');

        expect($result)->toBe(['name' => 'John Doe', 'address' => 'john@example.com']);
    });

    it('parses email with quoted name', function (): void {
        $method = $this->reflection->getMethod('parseEmailAddress');
        $method->setAccessible(true);

        $result = $method->invoke($this->mailer, '"Jane Doe" <jane@example.com>');

        expect($result)->toBe(['name' => 'Jane Doe', 'address' => 'jane@example.com']);
    });

    it('parses email without name in angle brackets', function (): void {
        $method = $this->reflection->getMethod('parseEmailAddress');
        $method->setAccessible(true);

        expect($method->invoke($this->mailer, '<noreply@example.com>'))->toBe('noreply@example.com');
    });

    it('parses multiple comma-separated addresses', function (): void {
        $method = $this->reflection->getMethod('parseEmailAddresses');
        $method->setAccessible(true);

        $result = $method->invoke($this->mailer, 'john@example.com, Jane <jane@example.com>');

        expect($result)->toHaveCount(2);
    });

    it('cleans email with extra whitespace and quotes', function (): void {
        $method = $this->reflection->getMethod('cleanEmailAddress');
        $method->setAccessible(true);

        expect($method->invoke($this->mailer, '  user@example.com  '))->toBe('user@example.com');
        expect($method->invoke($this->mailer, '"John" <john@test.com>'))->toBe('John <john@test.com>');
    });

    it('cleans array of email addresses', function (): void {
        $method = $this->reflection->getMethod('cleanEmailAddresses');
        $method->setAccessible(true);

        $result = $method->invoke($this->mailer, ['  a@b.com  ', '"X" <x@y.com>']);

        expect($result)->toBe(['a@b.com', 'X <x@y.com>']);
    });

    it('cleanEmailAddresses handles single string', function (): void {
        $method = $this->reflection->getMethod('cleanEmailAddresses');
        $method->setAccessible(true);

        expect($method->invoke($this->mailer, '  user@test.com  '))->toBe('user@test.com');
    });

    it('parseEmailAddresses filters out empty entries', function (): void {
        $method = $this->reflection->getMethod('parseEmailAddresses');
        $method->setAccessible(true);

        $result = $method->invoke($this->mailer, 'a@b.com, , c@d.com');

        expect($result)->toHaveCount(2);
    });
});

describe('Mailer header processing', function (): void {
    it('processHeaders handles empty string', function (): void {
        $method = $this->reflection->getMethod('processHeaders');
        $method->setAccessible(true);

        $mail = Mockery::mock(Message::class);
        // Should not throw - empty headers are no-ops
        $method->invoke($this->mailer, $mail, '');

        expect(true)->toBeTrue();
    });

    it('processHeaders handles empty array', function (): void {
        $method = $this->reflection->getMethod('processHeaders');
        $method->setAccessible(true);

        $mail = Mockery::mock(Message::class);
        $method->invoke($this->mailer, $mail, []);

        expect(true)->toBeTrue();
    });

    it('processHeaders skips headers without colon', function (): void {
        $method = $this->reflection->getMethod('processHeaders');
        $method->setAccessible(true);

        $mail = Mockery::mock(Message::class);
        // No methods should be called on $mail since invalid headers are skipped
        $method->invoke($this->mailer, $mail, ['InvalidHeader']);

        expect(true)->toBeTrue();
    });

    it('processHeaders parses newline-separated string headers', function (): void {
        $method = $this->reflection->getMethod('processHeaders');
        $method->setAccessible(true);

        $mail = Mockery::mock(Message::class);
        $mail->shouldReceive('replyTo')->once();
        $mail->shouldReceive('cc')->once();

        $method->invoke($this->mailer, $mail, "Reply-To: reply@test.com\nCC: cc@test.com");

        expect(true)->toBeTrue();
    });

    it('processHeaders handles array of header strings', function (): void {
        $method = $this->reflection->getMethod('processHeaders');
        $method->setAccessible(true);

        $mail = Mockery::mock(Message::class);
        $mail->shouldReceive('bcc')->once();

        $method->invoke($this->mailer, $mail, ['BCC: bcc@test.com']);

        expect(true)->toBeTrue();
    });

    it('applyHeader sets CC recipients', function (): void {
        $method = $this->reflection->getMethod('applyHeader');
        $method->setAccessible(true);

        $mail = Mockery::mock(Message::class);
        $mail->shouldReceive('cc')->once();

        $method->invoke($this->mailer, $mail, 'cc', 'user@test.com');
    });

    it('applyHeader sets BCC recipients', function (): void {
        $method = $this->reflection->getMethod('applyHeader');
        $method->setAccessible(true);

        $mail = Mockery::mock(Message::class);
        $mail->shouldReceive('bcc')->once();

        $method->invoke($this->mailer, $mail, 'bcc', 'user@test.com');
    });

    it('applyHeader sets Reply-To', function (): void {
        $method = $this->reflection->getMethod('applyHeader');
        $method->setAccessible(true);

        $mail = Mockery::mock(Message::class);
        $mail->shouldReceive('replyTo')->once();

        $method->invoke($this->mailer, $mail, 'reply-to', 'reply@test.com');
    });

    it('applyHeader skips content-type', function (): void {
        $method = $this->reflection->getMethod('applyHeader');
        $method->setAccessible(true);

        $mail = Mockery::mock(Message::class);
        // No methods should be called

        $method->invoke($this->mailer, $mail, 'content-type', 'text/html');

        expect(true)->toBeTrue();
    });

    it('applyHeader adds custom header as text header', function (): void {
        $method = $this->reflection->getMethod('applyHeader');
        $method->setAccessible(true);

        $headers = Mockery::mock();
        $headers->shouldReceive('addTextHeader')->once()->with('x-custom', 'value');

        $mail = Mockery::mock(Message::class);
        $mail->shouldReceive('getHeaders')->andReturn($headers);

        $method->invoke($this->mailer, $mail, 'x-custom', 'value');
    });

    it('setFromHeader sets from with name and address', function (): void {
        $method = $this->reflection->getMethod('setFromHeader');
        $method->setAccessible(true);

        $mail = Mockery::mock(Message::class);
        $mail->shouldReceive('from')->once()->with('john@test.com', 'John Doe');

        $method->invoke($this->mailer, $mail, 'John Doe <john@test.com>');
    });

    it('setFromHeader sets from with address only', function (): void {
        $method = $this->reflection->getMethod('setFromHeader');
        $method->setAccessible(true);

        $mail = Mockery::mock(Message::class);
        $mail->shouldReceive('from')->once()->with('noreply@test.com');

        $method->invoke($this->mailer, $mail, 'noreply@test.com');
    });
});

describe('Mailer attachments', function (): void {
    it('addAttachments handles array of paths', function (): void {
        $method = $this->reflection->getMethod('addAttachments');
        $method->setAccessible(true);

        $mail = Mockery::mock(Message::class);
        $mail->shouldReceive('attach')->twice();

        $method->invoke($this->mailer, $mail, ['/path/file1.pdf', '/path/file2.jpg']);
    });

    it('addAttachments handles newline-separated string', function (): void {
        $method = $this->reflection->getMethod('addAttachments');
        $method->setAccessible(true);

        $mail = Mockery::mock(Message::class);
        $mail->shouldReceive('attach')->twice();

        $method->invoke($this->mailer, $mail, "/path/file1.pdf\n/path/file2.jpg");
    });

    it('addAttachments filters empty entries', function (): void {
        $method = $this->reflection->getMethod('addAttachments');
        $method->setAccessible(true);

        $mail = Mockery::mock(Message::class);
        $mail->shouldReceive('attach')->once();

        $method->invoke($this->mailer, $mail, ['/path/file.pdf', '', '  ']);
    });

    it('addAttachments handles empty array', function (): void {
        $method = $this->reflection->getMethod('addAttachments');
        $method->setAccessible(true);

        $mail = Mockery::mock(Message::class);
        // attach should not be called
        $method->invoke($this->mailer, $mail, []);

        expect(true)->toBeTrue();
    });
});
