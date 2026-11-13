<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\BlockPattern\Infrastructure\Helpers\PatternDataProcessor;
use Pollora\BlockPattern\Infrastructure\Helpers\PatternValidator;

function contentPatternRegistry(array $registered = []): void
{
    if (! class_exists('WP_Block_Patterns_Registry')) {
        eval('class WP_Block_Patterns_Registry {
            public static array $registered = [];
            private static ?self $instance = null;
            public static function get_instance(): self { return self::$instance ??= new self; }
            public function is_registered(string $slug): bool { return in_array($slug, self::$registered, true); }
        }');
    }

    WP_Block_Patterns_Registry::$registered = $registered;
}

describe('PatternValidator', function (): void {
    it('accepts a pattern that has a slug and a title and is not registered yet', function (): void {
        contentPatternRegistry(['theme/other']);

        expect((new PatternValidator)->isValid(['slug' => 'theme/hero', 'title' => 'Hero'], '/p/hero.php'))->toBeTrue();
    });

    it('refuses a pattern that WordPress already knows', function (): void {
        contentPatternRegistry(['theme/hero']);
        Functions\expect('_doing_it_wrong')->never();

        expect((new PatternValidator)->isValid(['slug' => 'theme/hero', 'title' => 'Hero'], '/p/hero.php'))->toBeFalse();
    });

    it('refuses a pattern without a slug and says so', function (): void {
        contentPatternRegistry();
        $reports = [];
        Functions\when('_doing_it_wrong')->alias(function (string $function, string $message, string $version) use (&$reports): void {
            $reports[] = [$function, $message, $version];
        });

        $valid = (new PatternValidator)->isValid(['title' => 'Hero'], '/p/hero.php');

        expect($valid)->toBeFalse()
            ->and($reports)->toHaveCount(1)
            ->and($reports[0][0])->toBe('_register_theme_block_patterns')
            ->and($reports[0][1])->toContain('/p/hero.php')->toContain('"Slug" field missing')
            ->and($reports[0][2])->toBe('6.0.0');
    });

    it('refuses a pattern without a title and says so', function (): void {
        contentPatternRegistry();
        $reports = [];
        Functions\when('_doing_it_wrong')->alias(function (string $function, string $message) use (&$reports): void {
            $reports[] = $message;
        });

        $valid = (new PatternValidator)->isValid(['slug' => 'theme/hero', 'title' => ''], '/p/hero.php');

        expect($valid)->toBeFalse()
            ->and($reports)->toHaveCount(1)
            ->and($reports[0])->toContain('"Title" field missing');
    });
});

describe('PatternDataProcessor', function (): void {
    beforeEach(function (): void {
        $this->processor = new PatternDataProcessor;
        $this->theme = new class
        {
            public function get(string $header): string
            {
                return 'shop';
            }
        };
    });

    it('reads the pattern headers from the file', function (): void {
        $seen = null;
        Functions\when('get_file_data')->alias(function (string $file, array $headers) use (&$seen): array {
            $seen = [$file, $headers];

            return ['title' => 'Hero'];
        });

        expect($this->processor->getPatternData('/p/hero.php'))->toBe(['title' => 'Hero'])
            ->and($seen[0])->toBe('/p/hero.php')
            ->and($seen[1])->toMatchArray(['slug' => 'Slug', 'viewportWidth' => 'Viewport Width', 'inserter' => 'Inserter']);
    });

    it('converts lists, numbers and booleans and drops the empty values', function (): void {
        $processed = $this->processor->process([
            'slug' => 'theme/hero',
            'categories' => 'news,updates',
            'keywords' => '',
            'viewportWidth' => '800',
            'inserter' => 'Yes',
            'postTypes' => 'page',
            'blockTypes' => null,
        ], $this->theme);

        expect($processed)->toBe([
            'slug' => 'theme/hero',
            'categories' => ['news', 'updates'],
            'viewportWidth' => 800,
            'inserter' => true,
            'postTypes' => ['page'],
        ]);
    });

    it('treats an inserter header other than yes or true as disabled', function (): void {
        expect($this->processor->process(['slug' => 's', 'inserter' => 'nope'], $this->theme))->toBe(['slug' => 's']);
    });

    it('translates the title and the description with the theme text domain', function (): void {
        $calls = [];
        Functions\when('translate_with_gettext_context')->alias(function (string $text, string $context, string $domain) use (&$calls): string {
            $calls[] = [$context, $domain];

            return '['.$text.']';
        });

        $processed = $this->processor->process(['title' => 'Hero', 'description' => 'Banner'], $this->theme);

        expect($processed)->toBe(['title' => '[Hero]', 'description' => '[Banner]'])
            ->and($calls)->toBe([['Pattern title', 'shop'], ['Pattern description', 'shop']]);
    });

    it('keeps the text untouched for a theme that cannot give a text domain', function (): void {
        Functions\when('translate_with_gettext_context')->alias(fn (string $text): string => '['.$text.']');

        expect($this->processor->process(['title' => 'Hero'], new stdClass))->toBe(['title' => 'Hero']);
    });

    it('keeps the text untouched when the text domain is not a string', function (): void {
        Functions\when('translate_with_gettext_context')->alias(fn (string $text): string => '['.$text.']');
        $theme = new class
        {
            public function get(string $header): bool
            {
                return false;
            }
        };

        expect($this->processor->process(['title' => 'Hero'], $theme))->toBe(['title' => 'Hero']);
    });
});
