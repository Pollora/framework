<?php

declare(strict_types=1);

namespace Pollora\Support;

use Pollora\Services\Translater;

/**
 * Trait providing translation functionality for WordPress arguments.
 *
 * This trait offers methods to translate arrays of arguments commonly used
 * in WordPress, such as labels and names for post types and taxonomies.
 */
trait ArgumentTranslater
{
    /**
     * Translate the arguments using the given entity and keys.
     *
     * Translates specific keys within an array of arguments using a translation domain.
     * Supports nested keys and wildcards for array traversal.
     *
     * @param  array<string, mixed>  $args  The arguments to be translated (passed by reference)
     * @param  string  $entity  The translation domain/entity to use
     * @param  array<int, string>  $keyToTranslate  The keys to be translated (default: [
     *                                              'label',
     *                                              'labels.*',
     *                                              'names.singular',
     *                                              'names.plural',
     *                                              ])
     * @return array<string, mixed> The translated arguments
     */
    protected function translateArguments(
        array $args,
        string $entity,
        array $keyToTranslate = [
            'label',
            'labels.*',
            'names.singular',
            'names.plural',
        ]
    ): array {
        $translater = new Translater($args, $entity);

        return $translater->translate($keyToTranslate);
    }
}
