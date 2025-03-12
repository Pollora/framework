<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Option;

/**
 * Base class for all option-related events.
 *
 * This abstract class provides the foundation for all option events,
 * containing the option name and its old and new values.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
abstract class OptionEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        public readonly string $optionName,
        public readonly mixed $oldValue,
        public readonly mixed $newValue
    ) {}
}
