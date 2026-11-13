<?php

declare(strict_types=1);

/**
 * Stand-ins for the WordPress functions that load a translation catalogue, shared by the tests
 * that need them.
 *
 * They are real functions, not Brain Monkey stubs: a stub defined during a test outlives it, and the
 * first test that checks function_exists() afterwards would call a stub with no expectation. They must
 * also be defined once: a global function cannot be redeclared, so two test files that each declared
 * their own would see only the one that was loaded first. Each test sets what it needs on
 * WordPressI18nStandIn and the defaults come back after reset(): an en_US site, no translation file loaded.
 */
if (! defined('WP_LANG_DIR')) {
    define('WP_LANG_DIR', sys_get_temp_dir().'/pollora-wp-lang');
}

if (! class_exists('WordPressI18nStandIn', false)) {
    final class WordPressI18nStandIn
    {
        public static string $locale = 'en_US';

        /** @var list<string> the "domain|file" of every load_textdomain() call */
        public static array $loads = [];

        /** @var null|callable(string, string): bool */
        public static $loader;

        public static function reset(): void
        {
            self::$locale = 'en_US';
            self::$loads = [];
            self::$loader = null;
        }
    }
}

if (! function_exists('determine_locale')) {
    function determine_locale(): string
    {
        return WordPressI18nStandIn::$locale;
    }
}

if (! function_exists('load_textdomain')) {
    function load_textdomain(string $domain, string $mofile, ?string $locale = null): bool
    {
        WordPressI18nStandIn::$loads[] = $domain.'|'.$mofile;

        return WordPressI18nStandIn::$loader === null ? false : (WordPressI18nStandIn::$loader)($domain, $mofile);
    }
}
