<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Pollora\BlockPattern\Domain\Models\PatternFileData;
use Pollora\BlockPattern\Infrastructure\Adapters\WordPressPatternDataExtractor;
use Pollora\Collection\Infrastructure\Services\LaravelCollectionFactory;

function contentPatternTheme(mixed $textDomain = 'my-theme'): object
{
    return new class($textDomain)
    {
        public function __construct(private readonly mixed $textDomain) {}

        public function get(string $header): mixed
        {
            return $header === 'TextDomain' ? $this->textDomain : null;
        }
    };
}

describe('WordPressPatternDataExtractor', function (): void {
    beforeEach(function (): void {
        $this->extractor = new WordPressPatternDataExtractor(new LaravelCollectionFactory);
    });

    it('reads the headers of a pattern file with the pattern header names', function (): void {
        $seen = null;
        Functions\when('get_file_data')->alias(function (string $file, array $headers) use (&$seen): array {
            $seen = [$file, $headers];

            return ['title' => 'Hero', 'slug' => 'theme/hero'];
        });

        $data = $this->extractor->extractFromFile('/theme/patterns/hero.php');

        expect($data)->toBeInstanceOf(PatternFileData::class)
            ->and($data->getFile())->toBe('/theme/patterns/hero.php')
            ->and($data->getHeaders())->toBe(['title' => 'Hero', 'slug' => 'theme/hero'])
            ->and($seen[0])->toBe('/theme/patterns/hero.php')
            ->and($seen[1])->toMatchArray(['title' => 'Title', 'slug' => 'Slug', 'viewportWidth' => 'Viewport Width', 'blockTypes' => 'Block Types']);
    });

    it('splits the list headers on commas', function (): void {
        $data = new PatternFileData('/p.php', [
            'categories' => 'news,updates',
            'keywords' => 'foo,bar',
            'blockTypes' => 'core/paragraph',
            'postTypes' => 'page,post',
        ]);

        expect($this->extractor->processData($data, contentPatternTheme()))->toBe([
            'categories' => ['news', 'updates'],
            'keywords' => ['foo', 'bar'],
            'blockTypes' => ['core/paragraph'],
            'postTypes' => ['page', 'post'],
        ]);
    });

    it('casts the viewport width to an integer', function (): void {
        $processed = $this->extractor->processData(new PatternFileData('/p.php', ['viewportWidth' => '1200']), contentPatternTheme());

        expect($processed['viewportWidth'])->toBe(1200);
    });

    it('reads the inserter header as a boolean', function (string $header, bool $expected): void {
        $processed = $this->extractor->processData(new PatternFileData('/p.php', ['title' => 'T', 'inserter' => $header]), contentPatternTheme());

        expect($processed['inserter'] ?? false)->toBe($expected);
    })->with([
        'yes' => ['yes', true],
        'true in capitals' => ['TRUE', true],
        'one' => ['1', true],
        'no' => ['no', false],
        'anything else' => ['maybe', false],
    ]);

    it('drops the headers a pattern file leaves empty', function (): void {
        $processed = $this->extractor->processData(new PatternFileData('/p.php', [
            'title' => 'Hero',
            'slug' => '',
            'description' => null,
            'categories' => '',
            'viewportWidth' => '',
        ]), contentPatternTheme());

        expect($processed)->toBe(['title' => 'Hero']);
    });

    it('translates the title and the description in the text domain of the theme', function (): void {
        $calls = [];
        Functions\when('translate_with_gettext_context')->alias(function (string $text, string $context, string $domain) use (&$calls): string {
            $calls[] = [$text, $context, $domain];

            return strtoupper($text);
        });

        $processed = $this->extractor->processData(
            new PatternFileData('/p.php', ['title' => 'Hero', 'description' => 'A banner', 'slug' => 'theme/hero']),
            contentPatternTheme('shop'),
        );

        expect($processed)->toBe(['title' => 'HERO', 'description' => 'A BANNER', 'slug' => 'theme/hero'])
            ->and($calls)->toBe([['Hero', 'Pattern title', 'shop'], ['A banner', 'Pattern description', 'shop']]);
    });

    it('keeps the text as written when the theme has no usable text domain', function (mixed $domain): void {
        Functions\when('translate_with_gettext_context')->alias(fn (string $text): string => strtoupper($text));

        $processed = $this->extractor->processData(new PatternFileData('/p.php', ['title' => 'Hero']), contentPatternTheme($domain));

        expect($processed['title'])->toBe('Hero');
    })->with(['null' => [null], 'an array' => [['a']]]);

    it('keeps the text as written when the theme cannot be asked for a text domain', function (): void {
        Functions\when('translate_with_gettext_context')->alias(fn (string $text): string => strtoupper($text));

        $processed = $this->extractor->processData(new PatternFileData('/p.php', ['title' => 'Hero']), new stdClass);

        expect($processed['title'])->toBe('Hero');
    });

    describe('content', function (): void {
        beforeEach(function (): void {
            $this->previousApp = Facade::getFacadeApplication();
            $this->view = Mockery::mock();
            $app = new Container;
            $app->instance('view', $this->view);
            Facade::clearResolvedInstance('view');
            Facade::setFacadeApplication($app);
        });

        afterEach(function (): void {
            Facade::clearResolvedInstance('view');
            Facade::setFacadeApplication($this->previousApp);
        });

        it('renders the Blade view that matches the pattern file', function (): void {
            $this->view->shouldReceive('exists')->with('patterns/hero')->andReturn(true);
            $this->view->shouldReceive('make->render')->once()->andReturn('<p>Hero</p>');

            expect($this->extractor->getContent('/site/themes/shop/resources/views/patterns/hero.blade.php'))->toBe('<p>Hero</p>');
        });

        it('has no content for a pattern whose view does not exist', function (): void {
            $this->view->shouldReceive('exists')->with('patterns/hero')->andReturn(false);
            $this->view->shouldNotReceive('make');

            expect($this->extractor->getContent('/site/themes/shop/resources/views/patterns/hero.blade.php'))->toBeNull();
        });
    });
});
