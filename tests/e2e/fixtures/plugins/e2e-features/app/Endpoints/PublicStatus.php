<?php

declare(strict_types=1);

namespace Plugin\E2eFeatures\Endpoints;

use Pollora\Attributes\WpRestRoute;
use Pollora\Attributes\WpRestRoute\Method;
use Pollora\WpRest\Permissions\Everyone;

/**
 * Public on purpose: Everyone says so, a route that declares no permission is refused.
 */
#[WpRestRoute('e2e/v1', '/public', permissionCallback: Everyone::class)]
class PublicStatus
{
    /** @return array{route: string} */
    #[Method('GET')]
    public function show(): array
    {
        return ['route' => 'public'];
    }
}
