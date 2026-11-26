<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Installation;

use Illuminate\Support\Facades\File;
use Pollora\Services\WordPress\Database\EnvironmentFileHandler;

/**
 * The eight keys and salts of WordPress, in the `.env` of the project.
 *
 * WordPress signs its login cookies and nonces with them. `config/wordpress.php` reads them from the environment; without them WordPress
 * makes its own and keeps them in the database, next to the sessions they protect. A new project gets its own, once; a project that has
 * them keeps them, since changing them signs everyone out.
 */
class SaltsService
{
    public const array KEYS = [
        'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
        'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT',
    ];

    /** What `wp-config-sample.php` and many examples leave in place. */
    private const string SAMPLE = 'put your unique phrase here';

    public function __construct(private readonly EnvironmentFileHandler $envHandler) {}

    /**
     * Whether there is a `.env` to write to: a platform that gives its variables to the process has none, and none is made.
     */
    public function canWrite(): bool
    {
        return File::exists(base_path('.env'));
    }

    /**
     * The keys that have no value of their own, neither in the configuration (the environment of the process included) nor in `.env`.
     *
     * @return list<string>
     */
    public function missing(): array
    {
        $env = $this->canWrite() ? File::get(base_path('.env')) : '';

        return array_values(array_filter(self::KEYS, fn (string $key): bool => ! $this->isSet(config('wordpress.constants.'.strtolower($key)))
            && ! $this->isSet(preg_match('/^[ \t]*'.$key.'[ \t]*=[ \t]*["\']?([^"\'\r\n#]*)/m', $env, $found) === 1 ? trim($found[1]) : null)));
    }

    /**
     * Writes a new value for each of the keys given.
     *
     * @param  list<string>  $keys
     */
    public function generate(array $keys): void
    {
        $values = [];

        foreach (array_intersect(self::KEYS, $keys) as $key) {
            // 384 random bits, in characters a .env file takes as they are: no quote, space, `#` or `$`
            $values[$key] = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        }

        if ($values !== []) {
            $this->envHandler->updateEnvFile($values, append: true);
        }
    }

    private function isSet(mixed $value): bool
    {
        return is_string($value) && $value !== '' && ! str_contains(strtolower($value), self::SAMPLE);
    }
}
