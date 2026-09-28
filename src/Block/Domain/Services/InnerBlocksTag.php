<?php

declare(strict_types=1);

namespace Pollora\Block\Domain\Services;

/**
 * The `<InnerBlocks />` tag a block template writes where its inner blocks go.
 *
 * On the page, the tag gives way to the inner blocks' HTML, wrapped in a
 * `div` whose class is the tag's own `class` (or `className`), else
 * `pollora-inner-blocks`. The editor renders that same `div` around the
 * editable blocks, so a template styles one markup in both places.
 *
 * Gutenberg keeps a single list of inner blocks per block: the first tag
 * receives them, any further tag is dropped.
 */
final class InnerBlocksTag
{
    /**
     * Class of the wrapper when the tag names none.
     */
    public const string DEFAULT_CLASS = 'pollora-inner-blocks';

    /**
     * `<InnerBlocks … />` or `<InnerBlocks …></InnerBlocks>`. Attribute values
     * may not hold a raw `>`: JSON written with `{{ json_encode(…) }}` has it
     * escaped.
     */
    private const string PATTERN = '/<InnerBlocks\b([^>]*?)\s*(?:\/>|>\s*<\/InnerBlocks\s*>)/i';

    /**
     * Whether the HTML holds the tag.
     */
    public static function isPresentIn(string $html): bool
    {
        return preg_match(self::PATTERN, $html) === 1;
    }

    /**
     * Put the inner blocks' HTML where the first tag stands.
     */
    public static function replace(string $html, string $content): string
    {
        $isFirst = true;

        // A callback, not a replacement string: "$1" or "\1" in the inner
        // blocks' HTML must stay text.
        return (string) preg_replace_callback(self::PATTERN, static function (array $match) use (&$isFirst, $content): string {
            if (! $isFirst) {
                return '';
            }

            $isFirst = false;

            return sprintf('<div class="%s">%s</div>', htmlspecialchars(self::wrapperClass($match[1]), ENT_QUOTES), $content);
        }, $html);
    }

    /**
     * The wrapper class a tag's attributes name.
     */
    private static function wrapperClass(string $attributes): string
    {
        if (preg_match('/\b(?:class|className)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $attributes, $match) !== 1) {
            return self::DEFAULT_CLASS;
        }

        $class = trim(html_entity_decode($match[1] !== '' ? $match[1] : ($match[2] ?? ''), ENT_QUOTES | ENT_HTML5));

        return $class !== '' ? $class : self::DEFAULT_CLASS;
    }
}
