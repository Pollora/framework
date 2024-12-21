<?php

declare(strict_types=1);

namespace Pollora\Services;

/**
 * Service for handling translations in WordPress.
 *
 * This class provides functionality to translate arrays of strings using WordPress's
 * translation system. It supports nested arrays, wildcards, and domain-specific
 * translations.
 */
class Translater
{
    /**
     * Create a new translator instance.
     *
     * @param array<string, mixed> $items The items to be used in translations
     * @param string $domain The translation domain (defaults to 'wordpress')
     */
    public function __construct(
        protected array $items = [],
        protected string $domain = 'wordpress'
    ) {}

    /**
     * Translate an array of keys.
     *
     * Processes an array of keys for translation. Supports wildcards ('*')
     * for translating all items in an array or specific nested paths.
     *
     * @param array<int, string> $keysToTranslate The keys that need to be translated
     * @return array<string, mixed> The translated array
     *
     * @example
     * ```php
     * $translator = new Translater(['title' => 'Hello', 'desc' => 'World']);
     * $translated = $translator->translate(['*']); // Translates all items
     * $translated = $translator->translate(['title']); // Translates only title
     * ```
     */
    public function translate(array $keysToTranslate): array
    {
        if (in_array('*', $keysToTranslate, true)) {
            // Wildcard at the root, apply translation to all keys
            $this->recursiveTranslate($this->items);
        } else {
            foreach ($keysToTranslate as $key) {
                $this->translateKey($key);
            }
        }

        return $this->items;
    }

    /**
     * Translate a specific key in the items array.
     *
     * @param string $key The key to translate (supports dot notation for nested arrays)
     * @return void
     */
    protected function translateKey(string $key): void
    {
        if (str_contains($key, '.')) {
            // Handle nested keys
            $keys = explode('.', $key);
            $this->recursiveTranslateByKey($keys, $this->items);
        } elseif (isset($this->items[$key])) {
            $this->items[$key] = $this->translateItem($this->items[$key]);
        }
    }

    /**
     * Recursively translate nested array values by key.
     *
     * @param array<int, string> $keys The array of keys to traverse
     * @param array<string, mixed> $item Reference to the item array being modified
     * @return void
     */
    protected function recursiveTranslateByKey(array $keys, &$item): void
    {
        $currentKey = array_shift($keys);

        if ($currentKey === '*') {
            // Wildcard, apply translation to all nested keys
            foreach ($item as &$value) {
                if (is_array($value)) {
                    $this->recursiveTranslateByKey($keys, $value);
                } else {
                    $value = $this->translateItem($value);
                }
            }
        } elseif (isset($item[$currentKey])) {
            if ($keys === []) {
                // Last key reached, perform the translation
                $item[$currentKey] = $this->translateItem($item[$currentKey]);
            } else {
                // Keep traversing through the nested arrays
                $this->recursiveTranslateByKey($keys, $item[$currentKey]);
            }
        }
    }

    /**
     * Recursively translate all values in an array.
     *
     * @param array<string, mixed> $item Reference to the item being translated
     * @return void
     */
    protected function recursiveTranslate(&$item): void
    {
        foreach ($item as &$value) {
            if (is_array($value)) {
                $this->recursiveTranslate($value);
            } else {
                $value = $this->translateItem($value);
            }
        }
    }

    /**
     * Translate a single string value using WordPress translation function.
     *
     * @param string $value The string to translate
     * @return string The translated string
     */
    protected function translateItem(string $value): string
    {
        return str_replace($this->domain.'.', '', __($this->domain.'.'.$value));
    }
}
