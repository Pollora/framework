<?php

declare(strict_types=1);

namespace Pollen\Support;

use Illuminate\Support\Str;
use Pollen\Services\Translater;

/**
 * The ArgumentHelper class is a trait that provides methods to extract arguments from properties using getter methods.
 */
trait ArgumentTranslater
{
    /**
     * Translate the arguments using the given entity and key to translate
     *
     * @param  array  $args The arguments to be translated (passed by reference)
     * @param  string  $entity The entity used for translation
     * @param  array  $keyToTranslate The keys to be translated (default: [
     * 'label',
     * 'labels.*',
     * 'names.singular',
     * 'names.plural',
     * ])
     * @return void
     */
    protected function translateArguments(array $args, string $entity, array $keyToTranslate = [
        'label',
        'labels.*',
        'names.singular',
        'names.plural',
    ]): array
    {
        $translater = new Translater($args, $entity);
        return $translater->translate($keyToTranslate);
    }
}
