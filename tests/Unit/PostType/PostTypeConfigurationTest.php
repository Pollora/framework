<?php

declare(strict_types=1);

use Pollora\PostType\Infrastructure\Services\PostTypeConfiguration;

describe('PostTypeConfiguration', function (): void {
    beforeEach(function (): void {
        $this->config = new PostTypeConfiguration('book', 'Book', 'Books', ['public' => true]);
    });

    it('describes the post type it was built for', function (): void {
        expect($this->config->getSlug())->toBe('book')
            ->and($this->config->getName())->toBe('Book')
            ->and($this->config->getPluralName())->toBe('Books')
            ->and($this->config->withArgs())->toBe(['public' => true]);
    });

    it('sets, reads and defaults single arguments', function (): void {
        $this->config->setArg('menu_position', 7);

        expect($this->config->getArg('menu_position'))->toBe(7)
            ->and($this->config->getArg('missing'))->toBeNull()
            ->and($this->config->getArg('missing', 'fallback'))->toBe('fallback');
    });

    it('merges arguments with the new ones winning, and replaces them wholesale', function (): void {
        $this->config->mergeArgs(['public' => false, 'has_archive' => true]);

        expect($this->config->getArgs())->toBe(['public' => false, 'has_archive' => true]);

        $this->config->replaceArgs(['hierarchical' => true]);

        expect($this->config->getArgs())->toBe(['hierarchical' => true]);
    });

    it('keeps the priority out of the registration arguments and defaults it to 5', function (): void {
        expect($this->config->getPriority())->toBe(5);

        $this->config->setArg('priority', 9);

        expect($this->config->getPriority())->toBe(9)
            ->and($this->config->getArgs())->not->toHaveKey('priority');
    });

    it('gives the whole configuration for registration', function (): void {
        $this->config->setArg('priority', 3);

        expect($this->config->toArray())->toBe([
            'slug' => 'book',
            'singular' => 'Book',
            'plural' => 'Books',
            'args' => ['public' => true],
            'priority' => 3,
        ]);
    });
});
