<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Scouts;

use Illuminate\Support\ServiceProvider;
use Pollora\Discoverer\Infrastructure\Services\AbstractPolloraScout;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Spatie\StructureDiscoverer\Discover;

final class ThemeServiceProviderScout extends AbstractPolloraScout
{
    protected function criteria(Discover $discover): Discover
    {
        return $discover
            ->classes()
            ->extending(ServiceProvider::class);
    }
}
