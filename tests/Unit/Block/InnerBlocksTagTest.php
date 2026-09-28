<?php

declare(strict_types=1);

use Pollora\Block\Domain\Services\InnerBlocksTag;

describe('InnerBlocksTag', function (): void {
    it('puts the inner blocks where the tag stands, in the default wrapper', function (): void {
        expect(InnerBlocksTag::replace('<section><h2>Title</h2><InnerBlocks /></section>', '<p>Inner</p>'))
            ->toBe('<section><h2>Title</h2><div class="pollora-inner-blocks"><p>Inner</p></div></section>');
    });

    it('takes the wrapper class from the tag', function (string $tag): void {
        expect(InnerBlocksTag::replace($tag, 'x'))->toBe('<div class="card__body">x</div>');
    })->with([
        'class' => '<InnerBlocks class="card__body" />',
        'className, single quotes' => "<InnerBlocks className='card__body' />",
        'after other attributes' => '<InnerBlocks allowedBlocks="[&quot;core/paragraph&quot;]" class="card__body"/>',
        'with an end tag' => '<InnerBlocks class="card__body"></InnerBlocks>',
    ]);

    it('escapes the wrapper class', function (): void {
        expect(InnerBlocksTag::replace('<InnerBlocks class="a&quot;&gt;b" />', 'x'))
            ->toBe('<div class="a&quot;&gt;b">x</div>');
    });

    it('matches the tag whatever its attributes, over several lines', function (): void {
        $template = "<InnerBlocks\n    allowedBlocks=\"{{ json }}\"\n    templateLock=\"all\"\n/>";

        expect(InnerBlocksTag::replace($template, 'x'))->toBe('<div class="pollora-inner-blocks">x</div>');
    });

    it('keeps "$1" and backslashes of the inner blocks as text', function (): void {
        expect(InnerBlocksTag::replace('<InnerBlocks />', 'costs $1 \\1 \\\\'))
            ->toBe('<div class="pollora-inner-blocks">costs $1 \\1 \\\\</div>');
    });

    it('gives the inner blocks to the first tag and drops the others', function (): void {
        expect(InnerBlocksTag::replace('<InnerBlocks /><hr><InnerBlocks class="b" />', 'x'))
            ->toBe('<div class="pollora-inner-blocks">x</div><hr>');
    });

    it('leaves a template without the tag as it is', function (): void {
        expect(InnerBlocksTag::replace('<div>{{ $content }}</div><InnerBlocksList />', 'x'))
            ->toBe('<div>{{ $content }}</div><InnerBlocksList />')
            ->and(InnerBlocksTag::isPresentIn('<InnerBlocksList />'))->toBeFalse()
            ->and(InnerBlocksTag::isPresentIn('<innerblocks/>'))->toBeTrue();
    });
});
