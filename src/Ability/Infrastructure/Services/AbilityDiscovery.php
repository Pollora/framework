<?php

declare(strict_types=1);

namespace Pollora\Ability\Infrastructure\Services;

use Pollora\Abilities\Domain\Contracts\AbilityHandler;
use Pollora\Abilities\Factory\AbilityFactory;
use Pollora\Attributes\Ability;
use Pollora\Discovery\Domain\Contracts\DiscoveryInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Domain\Contracts\ReflectionCacheInterface;
use Pollora\Discovery\Domain\Services\HasInstancePool;
use Pollora\Discovery\Domain\Services\IsDiscovery;
use Psr\Log\LoggerInterface;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;

/**
 * Discovery service for `#[Ability]` attribute-decorated classes.
 *
 * Scans application, theme and module classes for those bearing the
 * {@see Ability} attribute, then queues each one on the `pollora/abilities`
 * factory. Nothing is published here: the factory holds the declarations until
 * the service provider flushes them on `wp_abilities_api_init`, which is the
 * only moment WordPress will accept them.
 *
 * The two-phase discover/apply workflow mirrors the other discoveries:
 *  1. **discover()** — collects the class name and its attribute into items.
 *  2. **apply()** — resolves each class from the instance pool and queues it.
 *
 * @see Ability         The PHP attribute scanned by this discovery.
 * @see AbilityHandler  The interface a decorated class must implement.
 * @see AbilityFactory  The factory declarations are queued on.
 */
final class AbilityDiscovery implements DiscoveryInterface
{
    use HasInstancePool;
    use IsDiscovery;

    /**
     * @param  AbilityFactory  $abilityFactory  The factory that queues abilities for registration.
     * @param  LoggerInterface|null  $logger  Optional logger for error reporting during the apply phase.
     */
    public function __construct(
        private readonly AbilityFactory $abilityFactory,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Discover classes annotated with `#[Ability]`.
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

            $attributes = (new \ReflectionClass($className))->getAttributes(Ability::class);

            if ($attributes === []) {
                return;
            }

            $this->getItems()->add($location, [
                'class' => $className,
                'attribute' => $attributes[0],
            ]);
        } catch (\Throwable) {
            // Skip classes that cannot be reflected (missing dependencies, etc.)
        }
    }

    /**
     * Queue every discovered ability on the factory.
     *
     * A class that carries the attribute without implementing
     * {@see AbilityHandler} is reported rather than skipped silently: the
     * attribute is an explicit statement of intent, so failing to honour it is a
     * mistake worth surfacing.
     */
    public function apply(): void
    {
        foreach ($this->getItems() as $discoveredItem) {
            [
                'class' => $className,
                'attribute' => $abilityAttribute,
            ] = $discoveredItem;

            try {
                /** @var Ability $ability */
                $ability = $abilityAttribute->newInstance();

                $instance = $this->getInstanceFromPool($className);

                if (! $instance instanceof AbilityHandler) {
                    $this->logger?->error(sprintf(
                        'Class %s carries #[Ability] but does not implement %s; ability "%s" was not registered.',
                        $className,
                        AbilityHandler::class,
                        $ability->name,
                    ));

                    continue;
                }

                // An ability naming a category nobody declared fails to register
                // silently. Declaring a fallback here means the attribute alone
                // is enough; an explicit Ability::category() still wins.
                $this->abilityFactory->ensureCategory($ability->category);

                $this->abilityFactory->handle(
                    name: $ability->name,
                    handler: $instance,
                    category: $ability->category,
                    label: $ability->label,
                    description: $ability->description,
                    behaviour: $ability->behaviour,
                );
            } catch (\Throwable $e) {
                $this->logger?->error(
                    sprintf('Failed to register ability from %s: %s', $className, $e->getMessage()),
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
        return 'ability';
    }
}
