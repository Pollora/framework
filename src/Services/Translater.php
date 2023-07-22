<?php
declare(strict_types=1);

namespace Pollen\Services;

class Translater
{
    /**
     * The items to be used in translations.
     *
     * @var array
     */
    protected array $items;

    /**
     * The domain of the translation.
     *
     * @var string
     */
    protected string $domain;

    /**
     * Create a new translator instance.
     *
     * @param array $items
     * @param string $domain
     */
    public function __construct(array $items = [], string $domain = 'wordpress')
    {
        $this->items = $items;
        $this->domain = $domain;
    }

    /**
     * Translate method.
     *
     * This method is used to translate an array of keys.
     *
     * @param array $keysToTranslate The keys that need to be translated.
     * @return array The translated array.
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
     * Translate a key.
     *
     * @param string $key The key to translate.
     *
     * @return void
     */
    protected function translateKey(string $key): void
    {
        if (strpos($key, '.') !== false) {
            // Handle nested keys
            $keys = explode('.', $key);
            $this->recursiveTranslateByKey($keys, $this->items);
        } else {
            if (isset($this->items[$key])) {
                $this->items[$key] = $this->translateItem($this->items[$key]);
            }
        }
    }

    /**
     * Translates the values of nested arrays by key recursively.
     *
     * @param array $keys The array of keys to traverse.
     * @param array $item The item array to be modified.
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
            if (empty($keys)) {
                // Last key reached, perform the translation
                $item[$currentKey] = $this->translateItem($item[$currentKey]);
            } else {
                // Keep traversing through the nested arrays
                $this->recursiveTranslateByKey($keys, $item[$currentKey]);
            }
        }
    }

    /**
     * Recursively translates the given item.
     *
     * @param array $item The item to be translated.
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
     * Translates a given item using the specified domain.
     *
     * @param string $value The item to be translated.
     *
     * @return string The translated item.
     */
    protected function translateItem(string $value): string
    {
        return __($this->domain . '.' . $value);
    }
}
