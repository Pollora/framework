<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Scouts;

use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Pollora\Discoverer\Domain\Contracts\HandlerScoutInterface;
use Pollora\Discoverer\Infrastructure\Services\AbstractPolloraScout;
use Spatie\StructureDiscoverer\Discover;

final class ServiceProviderScout extends AbstractPolloraScout implements HandlerScoutInterface
{
    protected function criteria(Discover $discover): Discover
    {
        return $discover
            ->classes()
            ->extending(ServiceProvider::class);
    }

    public function handle(): void
    {
        $discoveredClasses = $this->get();
        foreach ($discoveredClasses as $discoveredClass) {
            $this->container->register($discoveredClass);
        }
    }
}
