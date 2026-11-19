<?php

declare(strict_types=1);

use Pollora\WordPress\Config\ConstantManager;
use Pollora\WordPress\Exceptions\UndefinedConstantException;

describe('ConstantManager', function (): void {
    beforeEach(function (): void {
        $this->manager = new ConstantManager;
        $this->name = 'POLLORA_CONTENT_TEST_'.strtoupper(bin2hex(random_bytes(4)));
    });

    it('holds a queued constant without defining it', function (): void {
        $this->manager->queue($this->name, 'queued');

        expect($this->manager->get($this->name))->toBe('queued')
            ->and(defined($this->name))->toBeFalse();
    });

    it('refuses to give a constant that was never queued', function (): void {
        $this->manager->get($this->name);
    })->throws(UndefinedConstantException::class);

    it('defines every queued constant when applied, then forgets the queue', function (): void {
        $other = $this->name.'_B';
        $this->manager->queue($this->name, 'one');
        $this->manager->queue($other, false);

        $this->manager->apply();

        expect(constant($this->name))->toBe('one')
            ->and(constant($other))->toBeFalse();

        $this->manager->get($this->name);
    })->throws(UndefinedConstantException::class);

    it('does not queue a constant that is already defined', function (): void {
        define($this->name, 'original');

        $this->manager->queue($this->name, 'replacement');
        $this->manager->apply();

        expect(constant($this->name))->toBe('original');
    });

    it('keeps the last value queued for a constant', function (): void {
        $this->manager->queue($this->name, 'first');
        $this->manager->queue($this->name, 'second');

        expect($this->manager->get($this->name))->toBe('second');
    });
});
