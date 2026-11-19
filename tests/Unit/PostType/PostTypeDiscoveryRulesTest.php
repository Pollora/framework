<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Attributes\PostType;
use Pollora\Attributes\PostType\HasArchive;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Services\ReflectionCache;
use Pollora\PostType\Domain\Contracts\PostTypeServiceInterface;
use Pollora\PostType\Infrastructure\Services\PostTypeDiscovery;
use Pollora\Translation\Infrastructure\Adapters\TranslatingEntityRegistry;
use Psr\Log\LoggerInterface;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;
use Spatie\StructureDiscoverer\Data\DiscoveredInterface;

#[PostType]
final class ContentBookReview {}

#[PostType('film', singular: 'Movie', plural: 'Movies')]
final class ContentFilmWithArgs
{
    /** @return array<string, mixed> */
    public function withArgs(): array
    {
        return ['menu_position' => 12, 'public' => true];
    }
}

#[PostType('portfolio', singular: 'Project', plural: 'Projects', textDomain: 'shop')]
#[HasArchive('work')]
final class ContentConfiguredPortfolio
{
    public function configuring(object $postType): void
    {
        $postType->setRawArgs(['menu_icon' => 'dashicons-art']);
    }
}

#[PostType('abstracts')]
abstract class ContentAbstractPostType {}

final class ContentUndecoratedPostType {}

#[PostType('boom')]
final class ContentExplodingPostType
{
    public function __construct()
    {
        throw new RuntimeException('cannot build');
    }
}

/**
 * @return array{list<array{slug: string, singular: string, plural: string, args: array<string, mixed>, priority: int}>, PostTypeDiscovery}
 */
function contentRunPostTypeDiscovery(array $classes, ?LoggerInterface $logger = null): array
{
    $registered = [];
    $service = Mockery::mock(PostTypeServiceInterface::class);
    $service->shouldReceive('register')->andReturnUsing(
        function (string $slug, string $singular, string $plural, array $args, int $priority) use (&$registered): object {
            $registered[] = ['slug' => $slug, 'singular' => $singular, 'plural' => $plural, 'args' => $args, 'priority' => $priority];

            return new stdClass;
        }
    );

    $discovery = new PostTypeDiscovery($service, $logger);
    $location = new DiscoveryLocation('Tests\\', __DIR__);

    foreach ($classes as $class) {
        $structure = is_string($class) ? DiscoveredClass::fromReflection(new ReflectionClass($class)) : $class;
        $discovery->discover($location, $structure, new ReflectionCache);
    }

    $discovery->apply();

    return [$registered, $discovery];
}

describe('PostTypeDiscovery rules', function (): void {
    it('identifies itself as the post type discovery', function (): void {
        $discovery = new PostTypeDiscovery(Mockery::mock(PostTypeServiceInterface::class));

        expect($discovery->getIdentifier())->toBe('post_types');
    });

    it('names a post type from its class when the attribute gives no name', function (): void {
        [$registered] = contentRunPostTypeDiscovery([ContentBookReview::class]);

        expect($registered)->toHaveCount(1)
            ->and($registered[0]['slug'])->toBe('content-book-review')
            ->and($registered[0]['singular'])->toBe('Content book review')
            ->and($registered[0]['plural'])->toBe('Content book reviews')
            ->and($registered[0]['priority'])->toBe(5)
            ->and($registered[0]['args']['labels'])->toBe([TranslatingEntityRegistry::DEFAULT_LABELS => ['post_type', 'Content book review', 'Content book reviews']])
            ->and($registered[0]['args']['text_domain'])->toBe('pollora');
    });

    it('adds the arguments the class returns from withArgs', function (): void {
        [$registered] = contentRunPostTypeDiscovery([ContentFilmWithArgs::class]);

        expect($registered[0]['args'])->toMatchArray(['menu_position' => 12, 'public' => true])
            ->and($registered[0]['args']['labels'])->toBe([TranslatingEntityRegistry::DEFAULT_LABELS => ['post_type', 'Movie', 'Movies']]);
    });

    it('only picks up concrete classes that carry the attribute', function (): void {
        [$registered] = contentRunPostTypeDiscovery([
            ContentAbstractPostType::class,
            ContentUndecoratedPostType::class,
            ContentFilmWithArgs::class,
            new DiscoveredInterface('Thing', __FILE__, 'Tests', [], []),
        ]);

        expect(array_column($registered, 'slug'))->toBe(['film']);
    });

    it('registers a class with configuring through the entity, at init', function (): void {
        $hooks = [];
        Functions\when('add_action')->alias(function (string $hook, callable $callback, int $priority = 10) use (&$hooks): bool {
            $hooks[] = [$hook, $callback, $priority];

            return true;
        });

        [$viaService] = contentRunPostTypeDiscovery([ContentConfiguredPortfolio::class]);

        $entity = (new ReflectionFunction($hooks[0][1]))->getClosureUsedVariables()['entity'];

        expect($viaService)->toBe([])
            ->and($hooks)->toHaveCount(1)
            ->and($hooks[0][0])->toBe('init')
            ->and($hooks[0][2])->toBe(5)
            ->and($entity->getSlug())->toBe('portfolio')
            ->and($entity->getArgs())->toMatchArray(['menu_icon' => 'dashicons-art', 'has_archive' => 'work']);
    });

    it('logs a class it cannot build and keeps registering the others', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->atLeast()->once()->withArgs(
            fn (string $message, array $context): bool => str_contains($message, ContentExplodingPostType::class)
                && $context['exception'] instanceof Throwable
        );

        [$registered] = contentRunPostTypeDiscovery([ContentExplodingPostType::class, ContentFilmWithArgs::class], $logger);

        expect(array_column($registered, 'slug'))->toContain('film');
    });
});
