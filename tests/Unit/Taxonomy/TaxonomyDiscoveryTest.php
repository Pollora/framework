<?php

declare(strict_types=1);

use Pollora\Attributes\Action;
use Pollora\Attributes\Taxonomy;
use Pollora\Attributes\Taxonomy\Hierarchical;
use Pollora\Attributes\Taxonomy\Labels;
use Pollora\Attributes\Taxonomy\MetaBoxCb;
use Pollora\Attributes\Taxonomy\ShowInRest;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Services\ReflectionCache;
use Pollora\Hook\Domain\Contract\Action as ActionService;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyServiceInterface;
use Pollora\Taxonomy\Infrastructure\Services\TaxonomyDiscovery;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;

#[Taxonomy('fixture_genre', singular: 'Genre', plural: 'Genres', objectType: 'book')]
#[Hierarchical]
#[ShowInRest]
#[Labels(menuName: 'Shelves')]
final class DiscoveredGenre
{
    /** @var list<string> */
    public array $calls = [];

    #[MetaBoxCb]
    public function metaBox(): void
    {
        $this->calls[] = 'metaBox';
    }

    #[Action('init')]
    public function onInit(): void
    {
        $this->calls[] = 'onInit';
    }
}

/**
 * Run the discovery over the class and return the arguments it registered.
 *
 * @return array<string, mixed>
 */
function registeredTaxonomyArgs(string $class): array
{
    $registered = [];
    $service = Mockery::mock(TaxonomyServiceInterface::class);
    $service->shouldReceive('register')->once()->andReturnUsing(
        function (string $slug, string|array $objectType, string $singular, string $plural, array $args) use (&$registered): object {
            $registered = $args;

            return new stdClass;
        }
    );

    $discovery = new TaxonomyDiscovery($service);
    $discovery->discover(new DiscoveryLocation('Tests\\', __DIR__), DiscoveredClass::fromReflection(new ReflectionClass($class)), new ReflectionCache);
    $discovery->apply();

    return $registered;
}

it('registers the arguments its attributes declare, labels merged', function (): void {
    $args = registeredTaxonomyArgs(DiscoveredGenre::class);

    expect($args)->toMatchArray(['hierarchical' => true, 'show_in_rest' => true])
        ->and($args['labels']['menu_name'])->toBe('Shelves')
        ->and($args['labels']['singular_name'])->toBe('Genre');
});

it('turns a method attribute into a callback on the declaring class', function (): void {
    $callback = registeredTaxonomyArgs(DiscoveredGenre::class)['meta_box_cb'];

    expect($callback[0])->toBeInstanceOf(DiscoveredGenre::class)
        ->and($callback[1])->toBe('metaBox');
});

it('leaves the actions of a taxonomy class to the hook discovery', function (): void {
    $actions = Mockery::mock(ActionService::class);
    $actions->shouldNotReceive('add');

    app()->instance(ActionService::class, $actions);

    registeredTaxonomyArgs(DiscoveredGenre::class);
});
