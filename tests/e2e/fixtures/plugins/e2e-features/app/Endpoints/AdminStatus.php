<?php

declare(strict_types=1);

namespace Plugin\E2eFeatures\Endpoints;

use Pollora\Attributes\WpRestRoute;
use Pollora\Attributes\WpRestRoute\Method;
use Pollora\WpRest\Permissions\IsAdmin;

/**
 * Administrators only; the id comes from the route.
 */
#[WpRestRoute('e2e/v1', '/admin/(?P<id>\d+)', permissionCallback: IsAdmin::class)]
class AdminStatus
{
    /** @return array{route: string, id: int} */
    #[Method('GET')]
    public function show(string $id): array
    {
        return ['route' => 'admin', 'id' => (int) $id];
    }
}
