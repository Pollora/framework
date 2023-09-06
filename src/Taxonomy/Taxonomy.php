<?php

declare(strict_types=1);

namespace Pollen\Taxonomy;

use Pollen\Entity\Taxonomy as BaseTaxonomy;
use Pollen\Support\ArgumentTranslater;
use Pollen\Entity\Traits\ArgumentTranslater as BaseArgumentTranslater;

class Taxonomy extends BaseTaxonomy
{
    use ArgumentTranslater, BaseArgumentTranslater {
        ArgumentTranslater::translateArguments insteadof BaseArgumentTranslater;
    }
}
