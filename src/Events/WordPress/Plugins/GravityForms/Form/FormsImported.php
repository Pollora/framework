<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\GravityForms\Form;

/**
 * Event fired when Gravity Forms forms are imported.
 *
 * This event is triggered after forms have been imported in the
 * Gravity Forms system.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class FormsImported
{
    /**
     * Constructor.
     *
     * @param  array  $forms  The imported forms
     */
    public function __construct(
        public readonly array $forms
    ) {}
}
