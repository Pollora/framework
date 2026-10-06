<?php

declare(strict_types=1);

namespace Plugin\E2eFeatures;

use Pollora\Attributes\BlockBinding;
use Pollora\Attributes\BlockBinding\BindingField;
use Pollora\BlockBinding\Domain\Models\BindingContext;
use Pollora\Meta\Domain\Models\MetaRecord;

/**
 * A block binding source declared by a plugin: its fields fill core blocks
 * and a Blade block of the e2e-blocks plugin.
 */
#[BlockBinding('e2e/event', label: 'E2E Event', postTypes: 'e2e_event')]
final class EventBinding
{
    #[BindingField(label: 'Seats')]
    public function seats(BindingContext $context): ?string
    {
        $event = $context->meta(Event::class);

        return $event instanceof MetaRecord ? sprintf('%d seats <for> you', $event->capacity) : null;
    }

    #[BindingField(label: 'Booking link', type: 'url')]
    public function bookingUrl(BindingContext $context): string
    {
        return home_url('/book/'.$context->postId);
    }
}
