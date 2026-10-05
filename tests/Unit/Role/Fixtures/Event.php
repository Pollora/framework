<?php

declare(strict_types=1);

namespace Tests\Unit\Role\Fixtures;

use Pollora\Attributes\PostType;
use Pollora\Attributes\PostType\CapabilityType;
use Pollora\Attributes\PostType\MapMetaCap;

#[PostType('event')]
#[CapabilityType('event')]
#[MapMetaCap]
class Event {}
