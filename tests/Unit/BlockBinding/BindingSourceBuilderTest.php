<?php

declare(strict_types=1);

use Pollora\BlockBinding\Application\Services\BindingSourceBuilder;
use Pollora\BlockBinding\Domain\Enums\BindingFieldType;
use Pollora\BlockBinding\Domain\Exceptions\InvalidBindingSourceException;
use Pollora\BlockBinding\Domain\Models\BindingSource;
use Tests\Unit\BlockBinding\Fixtures\ArrayField;
use Tests\Unit\BlockBinding\Fixtures\DuplicateField;
use Tests\Unit\BlockBinding\Fixtures\EventBinding;
use Tests\Unit\BlockBinding\Fixtures\InvokableBinding;
use Tests\Unit\BlockBinding\Fixtures\NoField;
use Tests\Unit\BlockBinding\Fixtures\NoNamespace;
use Tests\Unit\BlockBinding\Fixtures\NotASource;
use Tests\Unit\BlockBinding\Fixtures\PrivateField;
use Tests\Unit\BlockBinding\Fixtures\UnknownType;
use Tests\Unit\BlockBinding\Fixtures\UntypedField;
use Tests\Unit\BlockBinding\Fixtures\UppercaseName;

require_once __DIR__.'/Fixtures/Invalid.php';

it('builds a source and its fields from the attributes', function (): void {
    $source = (new BindingSourceBuilder)->build(EventBinding::class);

    expect($source->name)->toBe('acme/event')
        ->and($source->label)->toBe('Event')
        ->and($source->usesContext)->toBe(['postId', 'postType'])
        ->and($source->postTypes)->toBe(['event'])
        ->and(array_keys($source->fields))->toBe(['remaining_seats', 'booking_url', 'capacity', 'sold_out', 'summary', 'nothing', 'broken'])
        ->and($source->field('remaining_seats')->label)->toBe('Remaining seats')
        ->and($source->field('booking_url')->label)->toBe('Booking Url')
        ->and($source->field('booking_url')->type)->toBe(BindingFieldType::Url)
        ->and($source->field('capacity')->method)->toBe('seatCount')
        ->and($source->isInvokable())->toBeFalse();
});

it('builds an invokable source, labelled after its class', function (): void {
    $source = (new BindingSourceBuilder)->build(InvokableBinding::class);

    expect($source->isInvokable())->toBeTrue()
        ->and($source->label)->toBe('Invokable Binding')
        ->and($source->usesContext)->toBe([]);
});

it('refuses a source WordPress would reject or leave empty', function (string $class, string $message): void {
    expect(fn (): BindingSource => (new BindingSourceBuilder)->build($class))
        ->toThrow(InvalidBindingSourceException::class, $message);
})->with([
    'no attribute' => [NotASource::class, 'has no #[BlockBinding] attribute'],
    'uppercase name' => [UppercaseName::class, 'must be "namespace/name"'],
    'no namespace' => [NoNamespace::class, 'must be "namespace/name"'],
    'no field' => [NoField::class, 'mark a public method #[BindingField], or define __invoke()'],
    'untyped field' => [UntypedField::class, 'UntypedField::title() cannot be registered: declare its return type'],
    'array field' => [ArrayField::class, 'it returns array'],
    'field twice' => [DuplicateField::class, 'the field "title" is already answered by heading()'],
    'unknown type' => [UnknownType::class, 'the type "video" is unknown'],
    'private field' => [PrivateField::class, 'a field must be a public, non-static method'],
]);
