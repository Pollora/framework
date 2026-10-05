<?php

declare(strict_types=1);

namespace Tests\Unit\Role\Fixtures;

use Pollora\Attributes\Role;

#[Role('usher', label: 'Usher', inherits: 'subscriber', textDomain: 'events')]
final class Usher {}
