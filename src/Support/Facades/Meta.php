<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Meta\Application\Services\MetaAccessor;
use Pollora\Meta\Domain\Enums\MetaObjectType;
use Pollora\Meta\Domain\Models\MetaRecord;
use Pollora\Meta\Domain\Models\MetaSchema;

/**
 * Laravel facade for typed meta.
 *
 *     $event = Meta::of(Event::class, $postId);
 *     $event->capacity;                         // int
 *     $event->fill(['capacity' => 250])->save();
 *
 * `Event` is the class whose properties carry `#[Meta]`. Reads and writes go
 * through WordPress's meta API. `schemas()` and `schemaFor()` give the compiled
 * schemas to tooling and UI drivers; a driver's package registers it with
 * `extend()`.
 *
 * @method static MetaRecord of(string $class, int $objectId)
 * @method static list<MetaSchema> schemas()
 * @method static list<MetaSchema> schemaFor(MetaObjectType|string $objectType, ?string $subtype = null)
 * @method static void extend(string $name, string|\Closure $driver)
 *
 * @see MetaAccessor
 */
class Meta extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'wp.meta';
    }
}
