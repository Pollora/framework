<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Infrastructure\Services;

use Pollora\Taxonomy\Domain\Contracts\LabelTranslatorInterface;

/**
 * Translates taxonomy labels through `__()`, which Pollora hands to WordPress's catalogues.
 */
class WordPressLabelTranslator implements LabelTranslatorInterface
{
    public function translate(string $text, string $domain): string
    {
        $translated = __($text, $domain);

        return is_string($translated) ? $translated : $text;
    }
}
