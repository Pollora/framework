<?php

declare(strict_types=1);

namespace Pollora\PostType;

use Pollora\Entity\PostType as BasePostType;
use Pollora\Entity\Traits\ArgumentTranslater as BaseArgumentTranslater;
use Pollora\Support\ArgumentTranslater;

/**
 * Extended PostType class with enhanced argument translation capabilities.
 *
 * This class extends the base PostType functionality by combining two argument
 * translation implementations, prioritizing the local ArgumentTranslater over
 * the base implementation.
 *
 * @method array translateArguments(array $args) Translates registration arguments to WordPress format
 * @method static PostType make(string $name, ?string $singular = null, ?string $plural = null) Creates a new post type instance
 */
class PostType extends BasePostType
{
    /**
     * Use both ArgumentTranslater implementations with local version taking precedence.
     *
     * The trait aliasing ensures that the local ArgumentTranslater's translateArguments
     * method is used instead of the base version when both traits provide the same method.
     */
    use ArgumentTranslater, BaseArgumentTranslater {
        ArgumentTranslater::translateArguments insteadof BaseArgumentTranslater;
    }
}
