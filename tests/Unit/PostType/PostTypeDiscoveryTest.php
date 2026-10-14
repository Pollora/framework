<?php

declare(strict_types=1);

use Pollora\Attributes\Action;
use Pollora\Attributes\PostType;
use Pollora\Attributes\PostType\AdminCol;
use Pollora\Attributes\PostType\Chronological;
use Pollora\Attributes\PostType\HasArchive;
use Pollora\Attributes\PostType\Labels;
use Pollora\Attributes\PostType\Priority;
use Pollora\Attributes\PostType\RegisterMetaBoxCb;
use Pollora\Attributes\PostType\Supports;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Services\ReflectionCache;
use Pollora\Hook\Domain\Contract\Action as ActionService;
use Pollora\PostType\Domain\Contracts\PostTypeServiceInterface;
use Pollora\PostType\Infrastructure\Services\PostTypeDiscovery;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;

#[PostType('fixture_book', singular: 'Book', plural: 'Books')]
#[HasArchive('library')]
#[Supports(['title', 'excerpt'])]
#[Labels(menuName: 'Library')]
#[Chronological]
#[Priority(7)]
final class DiscoveredBook
{
    /** @var list<string> */
    public array $calls = [];

    #[AdminCol('isbn', 'ISBN', sortable: true)]
    public function isbnColumn(): string
    {
        return '978';
    }

    #[RegisterMetaBoxCb]
    public function metaBoxes(): void
    {
        $this->calls[] = 'metaBoxes';
    }

    #[Action('init')]
    public function onInit(): void
    {
        $this->calls[] = 'onInit';
    }
}

/**
 * Run the discovery over the given classes and return what reached the post type service.
 *
 * @return array{slug: string, singular: string, plural: string, args: array<string, mixed>, priority: int}
 */
function registeredPostType(string $class): array
{
    $registered = [];
    $service = Mockery::mock(PostTypeServiceInterface::class);
    $service->shouldReceive('register')->once()->andReturnUsing(
        function (string $slug, string $singular, string $plural, array $args, int $priority) use (&$registered): object {
            $registered = ['slug' => $slug, 'singular' => $singular, 'plural' => $plural, 'args' => $args, 'priority' => $priority];

            return new stdClass;
        }
    );

    $discovery = new PostTypeDiscovery($service);
    $discovery->discover(new DiscoveryLocation('Tests\\', __DIR__), DiscoveredClass::fromReflection(new ReflectionClass($class)), new ReflectionCache);
    $discovery->apply();

    return $registered;
}

it('registers the arguments its attributes declare', function (): void {
    $registered = registeredPostType(DiscoveredBook::class);

    expect($registered['slug'])->toBe('fixture_book')
        ->and($registered['priority'])->toBe(7)
        ->and($registered['args'])->toMatchArray([
            'has_archive' => 'library',
            'supports' => ['title', 'excerpt'],
            'orderby' => 'date',
            'order' => 'DESC',
            'text_domain' => 'pollora',
        ])
        ->and($registered['args'])->not->toHaveKey('priority');
});

it('merges declared labels into the generated ones', function (): void {
    $labels = registeredPostType(DiscoveredBook::class)['args']['labels'];

    expect($labels['menu_name'])->toBe('Library')
        ->and($labels['singular_name'])->toBe('Book');
});

it('turns method attributes into callbacks on the declaring class', function (): void {
    $args = registeredPostType(DiscoveredBook::class)['args'];

    expect($args['admin_cols']['isbn'])->toMatchArray(['title' => 'ISBN', 'sortable' => true])
        ->and($args['admin_cols']['isbn']['function'][0])->toBeInstanceOf(DiscoveredBook::class)
        ->and($args['admin_cols']['isbn']['function'][1])->toBe('isbnColumn')
        ->and($args['register_meta_box_cb'][0])->toBeInstanceOf(DiscoveredBook::class)
        ->and($args['register_meta_box_cb'][1])->toBe('metaBoxes');
});

it('leaves the actions of a post type class to the hook discovery', function (): void {
    $actions = Mockery::mock(ActionService::class);
    $actions->shouldNotReceive('add');

    app()->instance(ActionService::class, $actions);

    registeredPostType(DiscoveredBook::class);
});
