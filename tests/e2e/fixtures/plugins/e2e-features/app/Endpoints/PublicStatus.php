<?php

declare(strict_types=1);

namespace Plugin\E2eFeatures\Endpoints;

use Pollora\Attributes\WpRestRoute;
use Pollora\Attributes\WpRestRoute\Method;

/**
 * No permission declared: the route is public.
 */
#[WpRestRoute('e2e/v1', '/public')]
class PublicStatus
{
    /** @return array{route: string} */
    #[Method('GET')]
    public function show(): array
    {
        return ['route' => 'public'];
    }
}
