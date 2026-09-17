<?php

declare(strict_types=1);

namespace Pollora\Services;

/**
 * Translates the string values of a configuration array in place.
 *
 * Each value is looked up as `{$domain}.{$value}`, so a plain config array such
 * as `['menu-header' => 'Header menu']` can be translated through a Laravel
 * group file named after the domain (`lang/{locale}/menus.php`) without the
 * config itself calling `__()`. Values with no entry come back unchanged.
 *
 * Config files may equally call `__($text, 'my-theme')` directly and skip this
 * class entirely — which is what the default theme does, and the clearer of the
 * two options. Note that only the theme config files Pollora defers to `init`
 * (`menus.php`, `sidebars.php`, `templates.php`) may safely call `__()` at all.
 */
class Translater
{
    /**
     * Create a new translator instance.
     *
     * @param  array<string, mixed>  $items  The items to be used in translations
     * @param  string  $domain  Group name prefixing every lookup, e.g. 'menus'
     */
    public function __construct(
        protected array $items,
        protected string $domain
    ) {}

    /**
     * Translate an array of keys.
     *
     * Processes an array of keys for translation. Supports wildcards ('*')
     * for translating all items in an array or specific nested paths.
     *
     * @param  array<int, string>  $keysToTranslate  The keys that need to be translated
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
     * @param  string  $key  The key to translate (supports dot notation for nested arrays)
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
     * @param  array<int, string>  $keys  The array of keys to traverse
     * @param  array<string, mixed>  $item  Reference to the item array being modified
     */
    protected function recursiveTranslateByKey(array $keys, array &$item): void
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
     * @param  array<string, mixed>  $item  Reference to the item being translated
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
     * Translate a single value, leaving anything that is not a string alone.
     *
     * A config array reached through a wildcard holds more than strings —
     * booleans and ints among them — and this class declares strict types, so
     * non-strings are passed through rather than coerced.
     *
     * @param  mixed  $value  The value to translate
     * @return mixed The translated string, or the value untouched
     */
    protected function translateItem(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $prefix = $this->domain.'.';
        $line = __($prefix.$value);

        // Anchored to the start: a plain str_replace() would also strip the
        // prefix from the middle of a translated line that happens to contain it.
        return str_starts_with($line, $prefix)
            ? substr($line, strlen($prefix))
            : $line;
    }
}
