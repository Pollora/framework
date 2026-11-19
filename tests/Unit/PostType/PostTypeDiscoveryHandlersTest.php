<?php

declare(strict_types=1);

use Pollora\Attributes\PostType;
use Pollora\Attributes\PostType\ZzDiscoveryBrokenOnClass;
use Pollora\Attributes\PostType\ZzDiscoveryBrokenOnMethod;
use Pollora\Attributes\PostType\ZzDiscoveryHandlesClass;
use Pollora\Attributes\PostType\ZzDiscoveryHandlesMethod;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Services\ReflectionCache;
use Pollora\PostType\Domain\Contracts\PostTypeServiceInterface;
use Pollora\PostType\Infrastructure\Services\PostTypeDiscovery;
use Psr\Log\LoggerInterface;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;

require_once __DIR__.'/Fixtures/DiscoveryAttributes.php';

#[PostType('handled')]
#[ZzDiscoveryHandlesClass]
final class HandledByItsAttributesPostType
{
    #[ZzDiscoveryHandlesMethod]
    public function handledMethod(): string
    {
        return 'handled';
    }
}

#[PostType('broken-class-attr')]
#[ZzDiscoveryBrokenOnClass]
final class BrokenClassAttributePostType {}

#[PostType('broken-method-attr')]
final class BrokenMethodAttributePostType
{
    #[ZzDiscoveryBrokenOnMethod]
    public function broken(): string
    {
        return 'broken';
    }
}

/**
 * @return list<array<string, mixed>> The arguments each registered post type was given
 */
function handlersRunPostTypeDiscovery(string $class, ?LoggerInterface $logger = null, bool $withCache = true): array
{
    $registered = [];
    $service = Mockery::mock(PostTypeServiceInterface::class);
    $service->shouldReceive('register')->andReturnUsing(function (string $slug, string $singular, string $plural, array $args) use (&$registered): object {
        $registered[] = $args;

        return new stdClass;
    });

    $discovery = new PostTypeDiscovery($service, $logger);
    $discovery->discover(new DiscoveryLocation('Tests\\', __DIR__), DiscoveredClass::fromReflection(new ReflectionClass($class)), $withCache ? new ReflectionCache : null);
    $discovery->apply();

    return $registered;
}

describe('PostTypeDiscovery attribute handlers', function (): void {
    it('lets an attribute that handles itself change the arguments, at class level and at method level', function (): void {
        $registered = handlersRunPostTypeDiscovery(HandledByItsAttributesPostType::class);

        expect($registered)->toHaveCount(1)
            ->and($registered[0])->toMatchArray(['handled_class' => 'HandledByItsAttributesPostType', 'handled_method' => 'handledMethod']);
    });

    it('logs a class-level attribute that cannot be built and still registers the post type', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with(Mockery::on(fn (string $m): bool => str_contains($m, 'class-level attributes for \\'.BrokenClassAttributePostType::class)), Mockery::type('array'));

        expect(handlersRunPostTypeDiscovery(BrokenClassAttributePostType::class, $logger))->toHaveCount(1);
    });

    it('logs a method-level attribute that cannot be built and still registers the post type', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with(Mockery::on(fn (string $m): bool => str_contains($m, 'method-level attributes for \\'.BrokenMethodAttributePostType::class)), Mockery::type('array'));

        expect(handlersRunPostTypeDiscovery(BrokenMethodAttributePostType::class, $logger))->toHaveCount(1);
    });

    it('logs the class and registers nothing when discovery had no reflection cache', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with(Mockery::on(fn (string $m): bool => str_contains($m, 'Failed to process PostType for class \\'.HandledByItsAttributesPostType::class)), Mockery::type('array'));

        expect(handlersRunPostTypeDiscovery(HandledByItsAttributesPostType::class, $logger, false))->toBe([]);
    });

    it('builds a configurable entity with the extra arguments it is given', function (): void {
        $discovery = new PostTypeDiscovery(Mockery::mock(PostTypeServiceInterface::class));

        $entity = $discovery->createEntityForConfiguring('film', null, null, ['menu_position' => 12], 7);

        expect($entity->getSlug())->toBe('film')
            ->and($entity->getArgs())->toMatchArray(['menu_position' => 12]);
    });
});
