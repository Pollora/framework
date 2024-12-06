<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Installation;

use function Laravel\Prompts\error;
use function Laravel\Prompts\search;
use function Laravel\Prompts\spin;

class LanguageService
{
    private const API_URL = 'https://api.wordpress.org/translations/core/1.0/';

    /**
     * Default fallback languages if API fails
     */
    private const FALLBACK_LANGUAGES = [
        'en_US' => 'English (United States)',
        'fr_FR' => 'French (France) - Français',
        'es_ES' => 'Spanish (Spain) - Español',
        'de_DE' => 'German - Deutsch',
        'it_IT' => 'Italian - Italiano',
        'pt_BR' => 'Portuguese (Brazil) - Português',
        'ru_RU' => 'Russian - Русский',
        'ja_JP' => 'Japanese - 日本語',
        'zh_CN' => 'Chinese (China) - 简体中文',
    ];

    /**
     * Prompt user to select a language
     */
    public function promptForLanguage(): string
    {
        $languages = $this->getAvailableLanguages();

        return search(
            label: 'Select site language (type to search)',
            placeholder: 'Start typing language name...',
            options: function (string $value) use ($languages) {
                if ($value === '' || $value === '0') {
                    return $languages;
                }

                // Search in both language codes and names
                return collect($languages)
                    ->filter(function ($name, $code) use ($value): bool {
                        $searchValue = strtolower($value);

                        return str_contains(strtolower($code), $searchValue) ||
                            str_contains(strtolower($name), $searchValue);
                    })
                    ->all();
            },
            hint: 'Search by language name or code (e.g., "french" or "fr")',
            scroll: 5
        );
    }

    /**
     * Get available WordPress languages
     */
    private function getAvailableLanguages(): array
    {
        try {
            $response = spin(
                message: 'Fetching available languages...',
                callback: fn (): string|false => file_get_contents(self::API_URL)
            );

            if ($response === false) {
                throw new \RuntimeException('Failed to fetch languages from WordPress API');
            }

            return $this->parseLanguagesResponse($response);
        } catch (\Throwable) {
            error('Could not fetch languages. Using default options.');

            return self::FALLBACK_LANGUAGES;
        }
    }

    /**
     * Parse the API response and format languages
     */
    private function parseLanguagesResponse(string $response): array
    {
        $data = json_decode($response, true);

        if (! isset($data['translations'])) {
            return self::FALLBACK_LANGUAGES;
        }

        $languages = ['en_US' => 'English (United States)'];

        foreach ($data['translations'] as $translation) {
            $languages[$translation['language']] = sprintf(
                '%s - %s',
                $translation['english_name'],
                $translation['native_name']
            );
        }

        return $languages;
    }
}
