<?php

declare(strict_types=1);

use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Pollora\Services\WordPress\Installation\LanguageService;

/**
 * Stands in for the https stream wrapper, so that the WordPress translations API is never reached:
 * whatever the test put in $body is what the "server" answers, and null makes the request fail.
 */
final class TranslationsApiStandIn
{
    public static ?string $body = null;

    public mixed $context = null;

    private int $position = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return self::$body !== null;
    }

    public function stream_read(int $count): string|false
    {
        $chunk = substr((string) self::$body, $this->position, $count);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen((string) self::$body);
    }

    /** @return array<string, int> */
    public function stream_stat(): array
    {
        return [];
    }
}

beforeEach(function (): void {
    stream_wrapper_unregister('https');
    stream_wrapper_register('https', TranslationsApiStandIn::class);
    TranslationsApiStandIn::$body = null;
});

afterEach(function (): void {
    stream_wrapper_restore('https');
    Prompt::interactive(false);
    Prompt::fallbackWhen(false);
});

/**
 * @param  list<string>  $keys
 */
function chooseLanguage(array $keys): string
{
    Prompt::fake($keys);
    Prompt::interactive(true);

    return (new LanguageService)->promptForLanguage();
}

describe('LanguageService', function (): void {
    it('offers the languages WordPress publishes, English first, and returns the one that is picked', function (): void {
        TranslationsApiStandIn::$body = json_encode(['translations' => [
            ['language' => 'fr_FR', 'english_name' => 'French (France)', 'native_name' => 'Français'],
            ['language' => 'de_DE', 'english_name' => 'German', 'native_name' => 'Deutsch'],
        ]]);

        expect(chooseLanguage([Key::DOWN, Key::DOWN, Key::ENTER]))->toBe('fr_FR');
    });

    it('narrows the list as the user types, in the code or in the name', function (): void {
        TranslationsApiStandIn::$body = json_encode(['translations' => [
            ['language' => 'fr_FR', 'english_name' => 'French (France)', 'native_name' => 'Français'],
            ['language' => 'de_DE', 'english_name' => 'German', 'native_name' => 'Deutsch'],
        ]]);

        expect(chooseLanguage([...str_split('deutsch'), Key::DOWN, Key::ENTER]))->toBe('de_DE');
    });

    it('falls back to a built-in list when the answer is not JSON', function (): void {
        TranslationsApiStandIn::$body = '<html>maintenance</html>';

        expect(chooseLanguage([Key::DOWN, Key::DOWN, Key::ENTER]))->toBe('fr_FR');
    });

    it('falls back to a built-in list when the answer has no translations', function (): void {
        TranslationsApiStandIn::$body = json_encode(['error' => 'nope']);

        expect(chooseLanguage([Key::DOWN, Key::DOWN, Key::ENTER]))->toBe('fr_FR');
    });

    it('falls back to a built-in list when the API cannot be reached', function (): void {
        TranslationsApiStandIn::$body = null;
        $warnings = [];
        set_error_handler(function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        });

        try {
            $language = chooseLanguage([Key::DOWN, Key::DOWN, Key::ENTER]);
        } finally {
            restore_error_handler();
        }

        expect($language)->toBe('fr_FR')->and($warnings)->not->toBe([]);
    });
});
