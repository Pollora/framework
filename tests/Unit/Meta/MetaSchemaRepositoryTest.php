<?php

declare(strict_types=1);

use Pollora\Meta\Application\Services\MetaSchemaBuilder;
use Pollora\Meta\Application\Services\MetaSchemaRepository;
use Pollora\Meta\Domain\Exceptions\InvalidMetaDefinitionException;
use Tests\Unit\Meta\Fixtures\ArticleExtras;
use Tests\Unit\Meta\Fixtures\BillingProfile;
use Tests\Unit\Meta\Fixtures\BookGenre;
use Tests\Unit\Meta\Fixtures\CategoryExtras;
use Tests\Unit\Meta\Fixtures\Event;
use Tests\Unit\Meta\Fixtures\EventExtras;
use Tests\Unit\Meta\Fixtures\MemberProfile;
use Tests\Unit\Meta\Fixtures\PageExtras;

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
    'The meta key "capacity" of post "event" is declared twice, by Tests\Unit\Meta\Fixtures\Event and by Tests\Unit\Meta\Fixtures\EventExtras.'
);

it('refuses a key declared twice on objects the schemas share', function (string $first, string $second, string $message): void {
    $repository = new MetaSchemaRepository;
    $repository->add((new MetaSchemaBuilder)->build($first));

    expect(fn () => $repository->add((new MetaSchemaBuilder)->build($second)))->toThrow(InvalidMetaDefinitionException::class, $message);
})->with([
    'a post type in a list' => [ArticleExtras::class, PageExtras::class, 'The meta key "subtitle" of post "page" is declared twice'],
    'users' => [MemberProfile::class, BillingProfile::class, 'The meta key "job_title" of user is declared twice'],
]);

it('accepts the same key on objects the schemas do not share', function (): void {
    $repository = new MetaSchemaRepository;

    $repository->add((new MetaSchemaBuilder)->build(PageExtras::class));
    $repository->add((new MetaSchemaBuilder)->build(Event::class));
    $repository->add((new MetaSchemaBuilder)->build(CategoryExtras::class));
    $repository->add((new MetaSchemaBuilder)->build(BookGenre::class));

    expect($repository->all())->toHaveCount(4);
});
