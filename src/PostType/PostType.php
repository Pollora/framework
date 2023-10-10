<?php

declare(strict_types=1);

namespace Pollen\PostType;

use Pollen\Entity\PostType as BasePostType;
use Pollen\Entity\Traits\ArgumentTranslater as BaseArgumentTranslater;
use Pollen\Support\ArgumentTranslater;

class PostType extends BasePostType
{
    use ArgumentTranslater, BaseArgumentTranslater {
        ArgumentTranslater::translateArguments insteadof BaseArgumentTranslater;
    }
}
