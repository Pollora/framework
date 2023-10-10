<?php

declare(strict_types=1);

namespace Pollen\Taxonomy;

use Pollen\Entity\Taxonomy as BaseTaxonomy;
use Pollen\Entity\Traits\ArgumentTranslater as BaseArgumentTranslater;
use Pollen\Support\ArgumentTranslater;

class Taxonomy extends BaseTaxonomy
{
    use ArgumentTranslater, BaseArgumentTranslater {
        ArgumentTranslater::translateArguments insteadof BaseArgumentTranslater;
    }
}
