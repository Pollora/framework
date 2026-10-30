<?php

declare(strict_types=1);

namespace Plugin\E2eFeatures\Endpoints;

use Pollora\Attributes\WpRestRoute;
use Pollora\Attributes\WpRestRoute\Method;

/**
 * No permission declared: the route is refused, whoever asks.
 */
#[WpRestRoute('e2e/v1', '/undeclared')]
class Undeclared
{
    /** @return array{route: string} */
    #[Method('GET')]
    public function show(): array
    {
        return ['route' => 'undeclared'];
    }
}
