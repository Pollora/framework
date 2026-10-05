<?php

declare(strict_types=1);

namespace Pollora\Meta\Infrastructure\Services;

use Pollora\Attributes\PostType;
use Pollora\Attributes\Taxonomy;
use Pollora\Discovery\Domain\Contracts\DiscoveryInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Domain\Contracts\ReflectionCacheInterface;
use Pollora\Discovery\Domain\Services\IsDiscovery;
use Pollora\Meta\Application\Services\MetaSchemaBuilder;
use Pollora\Meta\Application\Services\MetaSchemaRepository;
use Pollora\Meta\Domain\Contracts\MetaRegistryInterface;
use Psr\Log\LoggerInterface;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;

/**
 * Discovers the `#[Meta]` properties of `#[PostType]` and `#[Taxonomy]` classes.
 *
 *  1. **discover()** — keeps the classes carrying one of those attributes.
 *  2. **apply()** — builds each class's schema, stores it for `Meta::of()` and
 *     queues it for `register_meta()`.
 *
 * A declaration that cannot be registered is logged with the class and property
 * named, and the other classes still register.
 */
final class MetaDiscovery implements DiscoveryInterface
{
    use IsDiscovery;

    private const array OWNER_ATTRIBUTES = [PostType::class, Taxonomy::class];

    public function __construct(
        private readonly MetaSchemaBuilder $builder,
        private readonly MetaSchemaRepository $schemas,
        private readonly MetaRegistryInterface $registry,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function discover(DiscoveryLocationInterface $location, DiscoveredStructure $structure, ?ReflectionCacheInterface $reflectionCache = null): void
    {
        if (! $structure instanceof DiscoveredClass || $structure->isAbstract) {
            return;
        }

        foreach ($structure->attributes as $attribute) {
            if (in_array($attribute->class, self::OWNER_ATTRIBUTES, true)) {
                $this->getItems()->add($location, ['class' => $structure->namespace.'\\'.$structure->name]);

                return;
            }
        }
    }

    public function apply(): void
    {
        foreach ($this->getItems() as $item) {
            /** @var class-string $class */
            $class = $item['class'];

            try {
                $schema = $this->builder->build($class);

                if ($schema->isEmpty()) {
                    continue;
                }

                $this->schemas->add($schema);
                $this->registry->register($schema);
            } catch (\Throwable $throwable) {
                $this->logger?->error(sprintf('Failed to register the meta of %s: %s', $class, $throwable->getMessage()), ['exception' => $throwable]);
            }
        }
    }

    public function getIdentifier(): string
    {
        return 'meta';
    }
}
