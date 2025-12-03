<?php

declare(strict_types=1);

namespace Pollora\Hook\Infrastructure\Services;

use Pollora\Attributes\Action;
use Pollora\Attributes\Filter;
use Pollora\Discovery\Domain\Contracts\DiscoveryInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Domain\Services\IsDiscovery;
use Pollora\Discovery\Domain\Services\HasInstancePool;
use Pollora\Hook\Domain\Contracts\Action as ActionContract;
use Pollora\Hook\Domain\Contracts\Filter as FilterContract;
use ReflectionClass;
use ReflectionMethod;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;

/**
 * Hook Discovery
 *
 * Discovers methods decorated with Action and Filter attributes and registers them
 * as WordPress hooks. This discovery class scans for methods that have
 * the #[Action] or #[Filter] attributes and processes them through the
 * Hook services for registration.
 */
final class HookDiscovery implements DiscoveryInterface
{
    use IsDiscovery, HasInstancePool;

    /**
     * Create a new Hook discovery
     *
     * @param  ActionContract  $actionService  The action service for hook registration
     * @param  FilterContract  $filterService  The filter service for hook registration
     */
    public function __construct(
        private readonly ActionContract $actionService,
        private readonly FilterContract $filterService
    ) {}

    /**
     * {@inheritDoc}
     *
     * Discovers methods with Action and Filter attributes and collects them for registration.
     * Only processes public methods that have hook attributes.
     */
    public function discover(DiscoveryLocationInterface $location, DiscoveredStructure $structure): void
    {
        // Only process classes
        if (! $structure instanceof \Spatie\StructureDiscoverer\Data\DiscoveredClass) {
            return;
        }

        // Skip abstract classes
        if ($structure->isAbstract) {
            return;
        }

        try {
            // Use reflection to examine methods for hook attributes
            $reflectionClass = new ReflectionClass($structure->namespace.'\\'.$structure->name);

            foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                // Check for Action attributes
                $actionAttributes = $method->getAttributes(Action::class);
                foreach ($actionAttributes as $actionAttribute) {
                    $this->getItems()->add($location, [
                        'type' => 'action',
                        'class' => $structure->namespace.'\\'.$structure->name,
                        'method' => $method->getName(),
                        'attribute' => $actionAttribute,
                        'reflection_method' => $method,
                    ]);
                }

                // Check for Filter attributes
                $filterAttributes = $method->getAttributes(Filter::class);
                foreach ($filterAttributes as $filterAttribute) {
                    $this->getItems()->add($location, [
                        'type' => 'filter',
                        'class' => $structure->namespace.'\\'.$structure->name,
                        'method' => $method->getName(),
                        'attribute' => $filterAttribute,
                        'reflection_method' => $method,
                    ]);
                }
            }
        } catch (\Throwable) {
            // Skip classes that can't be reflected
            // This might happen for classes with missing dependencies
            return;
        }
    }

    /**
     * {@inheritDoc}
     *
     * Applies discovered hook methods by registering them through the Hook services.
     * Each discovered method is registered as an action or filter hook.
     */
    public function apply(): void
    {
        foreach ($this->getItems() as $discoveredItem) {
            [
                'type' => $hookType,
                'class' => $className,
                'method' => $methodName,
                'attribute' => $hookAttribute,
                'reflection_method' => $reflectionMethod
            ] = $discoveredItem;

            try {
                if ($hookType === 'action') {
                    /** @var Action $action */
                    $action = $hookAttribute->newInstance();

                    // Create instance and call method directly
                    $instance = $this->getInstanceFromPool($className);
                    $this->actionService->add(
                        hooks: $action->hook,
                        callback: [$instance, $methodName],
                        priority: $action->priority
                    );
                } elseif ($hookType === 'filter') {
                    /** @var Filter $filter */
                    $filter = $hookAttribute->newInstance();

                    // Create instance and call method directly
                    $instance = $this->getInstanceFromPool($className);
                    $this->filterService->add(
                        hooks: $filter->hook,
                        callback: [$instance, $methodName],
                        priority: $filter->priority
                    );
                }
            } catch (\Throwable $e) {
                // Log the error but continue with other hooks
                // In a production environment, you might want to use a proper logger
                error_log("Failed to register {$hookType} hook from method {$className}::{$methodName}: ".$e->getMessage());
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifier(): string
    {
        return 'hooks';
    }
}
