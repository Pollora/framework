<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\GravityForms;

/**
 * Base class for all Gravity Forms related events.
 *
 * This abstract class provides the foundation for all Gravity Forms events,
 * containing common properties and methods.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
abstract class GravityFormsEvent
{
    /**
     * Constructor.
     *
     * @param array $form The Gravity Forms form data
     */
    public function __construct(
        public readonly array $form
    ) {
    }
} 