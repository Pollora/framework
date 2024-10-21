<?php

declare(strict_types=1);

namespace Pollora\PostType;

use Pollora\Entity\PostType as BasePostType;
use Pollora\Entity\Traits\ArgumentTranslater as BaseArgumentTranslater;
use Pollora\Support\ArgumentTranslater;

class PostType extends BasePostType
{
    use ArgumentTranslater, BaseArgumentTranslater {
        ArgumentTranslater::translateArguments insteadof BaseArgumentTranslater;
    }
}
