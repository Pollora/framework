<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Meta\Application\Services\MetaAccessor;
use Pollora\Meta\Domain\Models\MetaRecord;

/**
 * Laravel facade for typed meta.
 *
 *     $event = Meta::of(Event::class, $postId);
 *     $event->capacity;                         // int
 *     $event->fill(['capacity' => 250])->save();
 *
 * `Event` is the `#[PostType]` (or `#[Taxonomy]`) class whose properties carry
 * `#[Meta]`. Reads and writes go through WordPress's meta API.
 *
 * @method static MetaRecord of(string $class, int $objectId)
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
