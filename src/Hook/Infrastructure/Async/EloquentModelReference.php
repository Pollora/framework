<?php

declare(strict_types=1);

namespace Pollora\Hook\Infrastructure\Async;

use Illuminate\Database\Eloquent\Model;
use Pollora\Hook\Async\Contracts\ObjectReference;

/**
 * Carries Eloquent models by class, key and connection, and reloads them at execution.
 *
 * The principle of SerializesModels: no stale data and a small payload.
 */
final class EloquentModelReference implements ObjectReference
{
    public function name(): string
    {
        return 'eloquent';
    }

    public function supports(object $object): bool
    {
        return $object instanceof Model && $object->exists;
    }

    public function reference(object $object): array
    {
        /** @var Model $object */
        $reference = ['class' => $object::class, 'key' => $object->getKey()];

        if ($object->getConnectionName() !== null) {
            $reference['connection'] = $object->getConnectionName();
        }

        return $reference;
    }

    public function resolve(array $reference): ?object
    {
        $class = $reference['class'] ?? null;

        if (! is_string($class) || ! is_a($class, Model::class, true)) {
            return null;
        }

        $query = isset($reference['connection']) && is_string($reference['connection'])
            ? $class::on($reference['connection'])
            : $class::query();

        return $query->find($reference['key'] ?? null);
    }
}
