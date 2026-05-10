<?php

declare(strict_types=1);

use Pollora\Hashing\WordPressHasher;

beforeEach(function (): void {
    $this->hasher = new WordPressHasher;
});

describe('WordPressHasher', function (): void {
    it('implements Laravel Hasher contract', function (): void {
        expect($this->hasher)->toBeInstanceOf(\Illuminate\Contracts\Hashing\Hasher::class);
    });

    it('hashes value via wp_hash_password', function (): void {
        Brain\Monkey\Functions\expect('wp_hash_password')
            ->once()
            ->with('my-password')
            ->andReturn('$P$BhashedValue');

        expect($this->hasher->make('my-password'))->toBe('$P$BhashedValue');
    });

    it('checks password via wp_check_password', function (): void {
        Brain\Monkey\Functions\when('wp_check_password')->justReturn(true);

        expect($this->hasher->check('plain', '$P$Bhashed'))->toBeTrue();
    });

    it('passes user_id option to wp_check_password', function (): void {
        Brain\Monkey\Functions\expect('wp_check_password')
            ->once()
            ->with('plain', '$P$Bhashed', 42)
            ->andReturn(true);

        expect($this->hasher->check('plain', '$P$Bhashed', ['user_id' => 42]))->toBeTrue();
    });

    it('detects md5 hashes need rehashing', function (): void {
        // MD5 = 32 chars → needs rehash
        expect($this->hasher->needsRehash('098f6bcd4621d373cade4e832627b4f6'))->toBeTrue();
    });

    it('detects phpass hashes do not need rehashing', function (): void {
        // PHPass format ($P$B...) is 34+ chars → does not need rehash
        expect($this->hasher->needsRehash('$P$B12345678901234567890123456789012'))->toBeFalse();
    });

    it('returns empty array for info()', function (): void {
        expect($this->hasher->info('$P$Bhashed'))->toBe([]);
    });
});
