<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Domain\Contracts;

/**
 * Translates the label templates a taxonomy builds its labels from.
 */
interface LabelTranslatorInterface
{
    /**
     * Translate a text in a text domain.
     *
     * @param  string  $text  The text to translate, e.g. 'All %s'
     * @param  string  $domain  The text domain, e.g. 'pollora'
     */
    public function translate(string $text, string $domain): string;
}
