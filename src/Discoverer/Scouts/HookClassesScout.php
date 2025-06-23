<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Scouts;

use Pollora\Attributes\AttributeProcessor;
use Pollora\Discoverer\Domain\Contracts\HandlerScoutInterface;
use Pollora\Discoverer\Infrastructure\Services\AbstractPolloraScout;
use Pollora\Hook\Domain\Contracts\Hooks;
use Spatie\StructureDiscoverer\Discover;

/**
 * Scout for discovering WordPress hook classes.
 *
 * This scout finds all classes that implement the Hooks interface,
 * typically located in Domain/Hooks directories within modules,
 * themes, and the main application.
 */
final class HookClassesScout extends AbstractPolloraScout implements HandlerScoutInterface
{
    /**
     * {@inheritDoc}
     */
    protected function criteria(Discover $discover): Discover
    {
        return $discover
            ->classes()
            ->implementing(Hooks::class);
    }

    /**
     * {@inheritDoc}
     */
    public function handle(): void
    {
        $discoveredClasses = $this->get();
        if (empty($discoveredClasses)) {
            return;
        }

        try {
            $processor = new AttributeProcessor($this->container);


            foreach ($discoveredClasses as $hookClass) {
                $this->registerHook($hookClass, $processor);
            }
        } catch (\Throwable $e) {
            // Log error but don't break the application
            if (function_exists('error_log')) {
                error_log('Failed to handle hooks: '.$e->getMessage());
            }
        }
    }

    /**
     * Register a single hook class and process its attributes.
     *
     * @param  string  $hookClass  The fully qualified class name of the hook
     * @param  AttributeProcessor  $processor  The attribute processor
     */
    private function registerHook(string $hookClass, AttributeProcessor $processor): void
    {
        $hookInstance = $this->container->make($hookClass);

        if (! $hookInstance instanceof Hooks) {
            return;
        }

        // Process attributes to register WordPress hooks (actions/filters)
        $processor->process($hookInstance);
    }
}
