<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use Pollora\BlockPattern\Infrastructure\Adapters\WordPressPatternDataExtractor;
use Pollora\Collection\Domain\Contracts\CollectionFactoryInterface;

beforeEach(function (): void {
    $this->themeDir = sys_get_temp_dir().'/pollora-pattern-content-'.uniqid();
    $this->patternsDir = $this->themeDir.'/resources/views/patterns';
    mkdir($this->patternsDir, 0755, true);

    View::addLocation($this->themeDir.'/resources/views');

    $this->extractor = new WordPressPatternDataExtractor(Mockery::mock(CollectionFactoryInterface::class));
});

afterEach(function (): void {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->themeDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }

    rmdir($this->themeDir);
});

describe('WordPressPatternDataExtractor::getContent()', function (): void {
    it('compiles and renders a .blade.php pattern', function (): void {
        file_put_contents(
            $this->patternsDir.'/hero.blade.php',
            '<!-- wp:paragraph --><p>{{ 1 + 1 }}</p><!-- /wp:paragraph -->'
        );

        expect($this->extractor->getContent($this->patternsDir.'/hero.blade.php'))
            ->toBe('<!-- wp:paragraph --><p>2</p><!-- /wp:paragraph -->');
    });

    it('reads a .html pattern back verbatim, with no compilation', function (): void {
        file_put_contents(
            $this->patternsDir.'/quote.html',
            '<!-- wp:quote --><blockquote>{{ 1 + 1 }}</blockquote><!-- /wp:quote -->'
        );

        // The literal "{{ 1 + 1 }}" must survive untouched — a .html pattern
        // is never handed to the Blade compiler.
        expect($this->extractor->getContent($this->patternsDir.'/quote.html'))
            ->toBe('<!-- wp:quote --><blockquote>{{ 1 + 1 }}</blockquote><!-- /wp:quote -->');
    });

    it('returns null for a missing .html pattern file', function (): void {
        expect($this->extractor->getContent($this->patternsDir.'/missing.html'))->toBeNull();
    });
});
