<?php

declare(strict_types=1);

namespace Pollora\Attributes\Taxonomy;

use Attribute;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyAttributeInterface;

/**
 * Attribute to set whether checked terms should appear on top.
 *
 * This allows you to override WordPress' default behaviour if necessary.
 * Default false if you're using a custom meta box, default true otherwise.
 *
 * @Attribute
 */
#[Attribute(Attribute::TARGET_CLASS)]
class CheckedOntop extends TaxonomyAttribute
{
    /**
     * Constructor.
     *
     * @param  bool  $value  Whether checked terms should appear on top
     */
    public function __construct(
        public readonly bool $value = true
    ) {}

    /**
     * Configure the taxonomy with the checked_ontop parameter.
     *
     * @param  Taxonomy  $taxonomy  The taxonomy to configure
     */
    protected function configure(TaxonomyAttributeInterface $taxonomy): void
    {
        $taxonomy->attributeArgs['checked_ontop'] = $this->value;
    }
}
