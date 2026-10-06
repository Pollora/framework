<?php

declare(strict_types=1);

use Pollora\BlockBinding\Domain\Models\BindingContext;
use Pollora\Meta\Application\Services\MetaAccessor;
use Pollora\Meta\Application\Services\MetaSchemaBuilder;
use Pollora\Meta\Application\Services\MetaSchemaRepository;
use Pollora\Meta\Domain\Services\MetaValueCaster;
use Tests\Unit\BlockBinding\Fixtures\ArrayMetaStore;
use Tests\Unit\BlockBinding\Fixtures\Concert;
use Tests\Unit\BlockBinding\Fixtures\VenueExtras;

beforeEach(function (): void {
    $store = new ArrayMetaStore(['post' => [7 => ['capacity' => '300']], 'term' => [3 => ['city' => 'Nantes']]]);
    $this->accessor = new MetaAccessor(new MetaSchemaRepository, new MetaSchemaBuilder, $store, new MetaValueCaster);
});

it('gives the arguments of the binding', function (): void {
    $context = new BindingContext(args: ['field' => 'date', 'format' => 'j F Y']);

    expect($context->arg('format'))->toBe('j F Y')
        ->and($context->arg('size', 'large'))->toBe('large');
});

it('reads typed meta on the object of the block context that carries them', function (): void {
    $context = new BindingContext(postId: 7, postType: 'concert', termId: 3, taxonomy: 'venue', metaAccessor: $this->accessor);

    expect($context->meta(Concert::class)?->capacity)->toBe(300)
        ->and($context->meta(VenueExtras::class)?->city)->toBe('Nantes');
});

it('has no meta to give without the object', function (): void {
    expect((new BindingContext(metaAccessor: $this->accessor))->meta(Concert::class))->toBeNull();
});
