<?php

declare(strict_types=1);

use Pollora\Meta\Application\Services\MetaSchemaBuilder;
use Pollora\Meta\Application\Services\MetaSchemaRepository;
use Pollora\Meta\Domain\Exceptions\InvalidMetaDefinitionException;
use Tests\Unit\Meta\Fixtures\BookGenre;
use Tests\Unit\Meta\Fixtures\Event;
use Tests\Unit\Meta\Fixtures\EventExtras;

it('keeps the schemas by declaring class', function (): void {
    $repository = new MetaSchemaRepository;
    $event = (new MetaSchemaBuilder)->build(Event::class);
    $genre = (new MetaSchemaBuilder)->build(BookGenre::class);

    $repository->add($event);
    $repository->add($genre);
    $repository->add($event);

    expect($repository->forClass(Event::class))->toBe($event)
        ->and($repository->forClass(EventExtras::class))->toBeNull()
        ->and($repository->all())->toBe([$event, $genre]);
});

it('refuses a key another class already declares on the same post type', function (): void {
    $repository = new MetaSchemaRepository;
    $repository->add((new MetaSchemaBuilder)->build(Event::class));

    $repository->add((new MetaSchemaBuilder)->build(EventExtras::class));
})->throws(
    InvalidMetaDefinitionException::class,
    'The meta key "capacity" of "event" is declared twice, by Tests\Unit\Meta\Fixtures\Event and by Tests\Unit\Meta\Fixtures\EventExtras.'
);
