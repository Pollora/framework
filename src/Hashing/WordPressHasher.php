<?php

declare(strict_types=1);

namespace Pollora\Hashing;

use Illuminate\Contracts\Hashing\Hasher as HasherContract;

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
     * @return array
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
     * @return string
     *
     * @throws \RuntimeException
     */
    public function make($value, array $options = [])
    {
        return wp_hash_password($value);
    }

    /**
     * Check the given plain value against a hash.
     *
     * @param  string  $value
     * @param  string  $hashedValue
     * @return bool
     */
    public function check($value, $hashedValue, array $options = [])
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
