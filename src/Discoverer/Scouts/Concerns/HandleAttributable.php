<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Scouts\Concerns;

use Illuminate\Contracts\Container\BindingResolutionException;
use Pollora\Attributes\Attributable;
use Pollora\Attributes\AttributeProcessor;

trait HandleAttributable
{
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

            foreach ($discoveredClasses as $attributableClass) {
                $this->processAttributable($attributableClass, $processor);
            }
        } catch (\Throwable $e) {
            // Log error but don't break the application
            if (function_exists('error_log')) {
                error_log('Failed to handle attributable classes: '.$e->getMessage());
            }
        }
    }

    /**
     * Process a single attributable class.
     *
     * @param  string  $attributableClass  The fully qualified class name of the attributable
     * @param  AttributeProcessor  $processor  The attribute processor
     *
     * @throws BindingResolutionException
     */
    private function processAttributable(string $attributableClass, AttributeProcessor $processor): void
    {
        $attributableInstance = $this->container->make($attributableClass);

        if (! $attributableInstance instanceof Attributable) {
            return;
        }

        // Process attributes for this attributable class
        $processor->process($attributableInstance);
    }
}
