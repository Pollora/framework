<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
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
});
