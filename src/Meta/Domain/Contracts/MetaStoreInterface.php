<?php

declare(strict_types=1);

namespace Pollora\Meta\Domain\Contracts;

use Pollora\Meta\Domain\Enums\MetaObjectType;

/**
 * Reads and writes stored meta values, through WordPress's meta API.
 */
interface MetaStoreInterface
{
    /**
     * The stored value, or null when the meta does not exist.
     */
    public function get(MetaObjectType $objectType, int $objectId, string $key): mixed;

    public function update(MetaObjectType $objectType, int $objectId, string $key, string $value): void;

    public function delete(MetaObjectType $objectType, int $objectId, string $key): void;
}
