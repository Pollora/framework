<?php

declare(strict_types=1);

namespace Pollora\Hashing;

use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use RuntimeException;

/**
 * Gives an interface to hash WordPress passwords from
 * within the Laravel environment.
 *
 * @author Jordan Doyle <jordan@doyle.wf>
 */
class WordPressHasher implements HasherContract
{
    /**
     * Get information about the given hashed value.
     *
     * @param  string  $hashedValue
     */
    /**
     * Get information about the given hashed value.
     *
     * @param  string  $hashedValue
     */
    public function info($hashedValue): array
    {
        return [];
    }

    /**
     * Hash the given value.
     *
     * @param  string  $value
     *
     * @throws RuntimeException
     */
    public function make(#[\SensitiveParameter] $value, array $options = []): string
    {
        return wp_hash_password($value);
    }

    /**
     * Check the given plain value against a hash.
     *
     * @param  string  $value
     * @param  string  $hashedValue
     */
    public function check(#[\SensitiveParameter] $value, $hashedValue, array $options = []): bool
    {
        return wp_check_password($value, $hashedValue, $options['user_id'] ?? '');
    }

    /**
     * Check if the given hash has been hashed using the given options.
     *
     * @param  string  $hashedValue
     */
    public function needsRehash($hashedValue, array $options = []): bool
    {
        // if the hashed value is md5 it needs rehashing.
        return strlen($hashedValue) <= 32;
    }
}
