<?php

declare(strict_types=1);

namespace Pollora\Ajax\Infrastructure\Services;

use Pollora\Ajax\AjaxAccess;
use Pollora\Ajax\Factory\AjaxFactory;
use Pollora\Attributes\Ajax;
use Pollora\Discovery\Domain\Contracts\DiscoveryInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Domain\Contracts\ReflectionCacheInterface;
use Pollora\Discovery\Domain\Services\HasInstancePool;
use Pollora\Discovery\Domain\Services\IsDiscovery;
use Pollora\Hook\Infrastructure\Services\HookDiscovery;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;

/**
 * Discovery service for `#[Ajax]` attribute-decorated methods.
 *
 * Scans application and theme classes for public methods bearing the
 * {@see Ajax} attribute, then registers each one as a WordPress AJAX
 * action through the {@see AjaxFactory} from the `pollora/ajax` package.
 *
 * The discovery/apply two-phase workflow mirrors {@see HookDiscovery}:
 *  1. **discover()** — collects method metadata (class, method, attribute) into items.
 *  2. **apply()** — instantiates classes from the pool and registers hooks.
 *
 * @see Ajax          The PHP attribute scanned by this discovery.
 * @see AjaxFactory   The factory used to create and register actions.
 */
final class AjaxDiscovery implements DiscoveryInterface
{
    use HasInstancePool;
    use IsDiscovery;

    /**
     * @param  AjaxFactory  $ajaxFactory  The factory that creates and auto-registers AJAX actions.
     * @param  LoggerInterface|null  $logger  Optional logger for error reporting during apply phase.
     */
    public function __construct(
        private readonly AjaxFactory $ajaxFactory,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Discover public methods annotated with `#[Ajax]`.
     *
     * Iterates over every public method of the given structure and collects
     * those bearing the {@see Ajax} attribute into the discovery items.
     *
     * @param  DiscoveryLocationInterface  $location  The scanned location (app, theme, module…).
     * @param  DiscoveredStructure  $structure  The class structure being inspected.
     * @param  ReflectionCacheInterface|null  $reflectionCache  Cached reflection data for performance.
     */
    public function discover(DiscoveryLocationInterface $location, DiscoveredStructure $structure, ?ReflectionCacheInterface $reflectionCache = null): void
    {
        if (! $structure instanceof DiscoveredClass) {
            return;
        }

        if ($structure->isAbstract) {
            return;
        }

        try {
            $className = $structure->namespace.'\\'.$structure->name;
            $reflectionClass = $reflectionCache->getClassReflection($className);

            foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(Ajax::class) as $attribute) {
                    $this->getItems()->add($location, [
                        'class' => $className,
                        'method' => $method->getName(),
                        'attribute' => $attribute,
                    ]);
                }
            }
        } catch (\Throwable) {
            // Skip classes that can't be reflected (missing dependencies, etc.)
        }
    }

    /**
     * Apply all discovered `#[Ajax]` methods by registering them as WordPress AJAX actions.
     *
     * For each discovered item:
     *  1. Instantiates the `#[Ajax]` attribute to read action name and access level.
     *  2. Resolves the class instance from the shared instance pool (with DI).
     *  3. Calls {@see AjaxFactory::listen()} to create the action.
     *  4. Applies the access level via {@see AjaxAccess::applyTo()}.
     */
    public function apply(): void
    {
        foreach ($this->getItems() as $discoveredItem) {
            [
                'class' => $className,
                'method' => $methodName,
                'attribute' => $ajaxAttribute,
            ] = $discoveredItem;

            try {
                /** @var Ajax $ajax */
                $ajax = $ajaxAttribute->newInstance();

                $instance = $this->getInstanceFromPool($className);

                $action = $this->ajaxFactory->listen(
                    $ajax->action,
                    [$instance, $methodName],
                );

                $ajax->access->applyTo($action);
            } catch (\Throwable $e) {
                $this->logger?->error(
                    sprintf('Failed to register Ajax action "%s" from %s::%s', $className, $methodName, $e->getMessage()),
                    ['exception' => $e]
                );
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifier(): string
    {
        return 'ajax';
    }
}
