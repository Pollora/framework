<?php

declare(strict_types=1);

use Pollora\Meta\Application\Services\MetaSchemaBuilder;
use Pollora\Meta\Domain\Exceptions\MetaValidationException;
use Tests\Unit\Meta\Fixtures\EventStatus;
use Tests\Unit\Meta\Fixtures\RatedEvent;

require_once __DIR__.'/Fixtures/validator.php';

beforeEach(function (): void {
    $this->schema = (new MetaSchemaBuilder)->build(RatedEvent::class);
});

it('passes a value that keeps the rules', function (): void {
    metaValidator()->validate($this->schema->definitions['capacity'], 5000);
    metaValidator()->validate($this->schema->definitions['status'], EventStatus::Published);
    metaValidator()->validate($this->schema->definitions['contact'], 'team@example.com');

    expect(true)->toBeTrue();
});

it('compares numbers as numbers, and names the meta by its label', function (): void {
    metaValidator()->validate($this->schema->definitions['capacity'], 6000);
})->throws(MetaValidationException::class, 'The meta "capacity" is invalid: The Capacity field must not be greater than 5000.');

it('checks an enum by its backing value', function (): void {
    metaValidator()->validate($this->schema->definitions['status'], EventStatus::Draft);
})->throws(MetaValidationException::class, 'The selected status is invalid.');

it('keeps the messages of the failed rules', function (): void {
    $caught = null;

    try {
        metaValidator()->validate($this->schema->definitions['contact'], 'nope');
    } catch (MetaValidationException $metaValidationException) {
        $caught = $metaValidationException;
    }

    expect($caught?->messages)->toBe(['The contact field must be a valid email address.'])
        ->and($caught?->definition->key)->toBe('contact');
});

it('skips a meta without rules', function (): void {
    metaValidator()->validate($this->schema->definitions['free'], -1);

    expect(true)->toBeTrue();
});

it('reads the rules from #[Meta]', function (): void {
    expect($this->schema->definitions['capacity']->rules)->toBe(['min:0', 'max:5000'])
        ->and($this->schema->definitions['free']->rules)->toBe([]);
});
