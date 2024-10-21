<?php

declare(strict_types=1);

namespace Pollora\Taxonomy;

use Pollora\Entity\Taxonomy as BaseTaxonomy;
use Pollora\Entity\Traits\ArgumentTranslater as BaseArgumentTranslater;
use Pollora\Support\ArgumentTranslater;

class Taxonomy extends BaseTaxonomy
{
    use ArgumentTranslater, BaseArgumentTranslater {
        ArgumentTranslater::translateArguments insteadof BaseArgumentTranslater;
    }
}
