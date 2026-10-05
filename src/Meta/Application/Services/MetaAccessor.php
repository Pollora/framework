<?php

declare(strict_types=1);

namespace Pollora\Meta\Application\Services;

use Pollora\Meta\Domain\Contracts\MetaStoreInterface;
use Pollora\Meta\Domain\Exceptions\InvalidMetaValueException;
use Pollora\Meta\Domain\Models\MetaDefinition;
use Pollora\Meta\Domain\Models\MetaRecord;
use Pollora\Meta\Domain\Models\MetaSchema;
use Pollora\Meta\Domain\Services\MetaValueCaster;
use Psr\Log\LoggerInterface;

/**
 * Gives the typed meta of a WordPress object: the service behind `Meta::of()`.
 *
 * A stored value that cannot be read as its declared type throws in debug mode,
 * so it shows during development; in production it reads as the property
 * default and is logged, so a damaged value never takes a public page down.
 */
final readonly class MetaAccessor
{
    public function __construct(
        private MetaSchemaRepository $schemas,
        private MetaSchemaBuilder $builder,
        private MetaStoreInterface $store,
        private MetaValueCaster $caster,
        private bool $debug = false,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * @param  class-string  $class  The class declaring the meta
     * @param  int  $objectId  The post, term, user or comment ID
     */
    public function of(string $class, int $objectId): MetaRecord
    {
        return $this->record($this->schemas->forClass($class) ?? $this->builder->build($class), $objectId);
    }

    /**
     * The typed meta of a schema on one object.
     */
    public function record(MetaSchema $schema, int $objectId): MetaRecord
    {
        return new MetaRecord($schema, $objectId, $this->store, $this->caster, $this->handleUnreadable(...));
    }

    private function handleUnreadable(InvalidMetaValueException $exception, MetaDefinition $definition): mixed
    {
        if ($this->debug) {
            throw $exception;
        }

        $this->logger?->warning($exception->getMessage());

        return $definition->default;
    }
}
