<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Mockery as m;
use Pollora\Attributes\Attributable;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Attributes\Taxonomy\AllowHierarchy;
use Pollora\Attributes\Taxonomy\Args;
use Pollora\Attributes\Taxonomy\CheckedOntop;
use Pollora\Attributes\Taxonomy\DefaultTerm;
use Pollora\Attributes\Taxonomy\Description;
use Pollora\Attributes\Taxonomy\Exclusive;
use Pollora\Attributes\Taxonomy\Hierarchical;
use Pollora\Attributes\Taxonomy\Label;
use Pollora\Attributes\Taxonomy\MetaBoxCb;
use Pollora\Attributes\Taxonomy\MetaBoxSanitizeCb;
use Pollora\Attributes\Taxonomy\ObjectType;
use Pollora\Attributes\Taxonomy\PublicTaxonomy;
use Pollora\Attributes\Taxonomy\QueryVar;
use Pollora\Attributes\Taxonomy\RestBase;
use Pollora\Attributes\Taxonomy\RestControllerClass;
use Pollora\Attributes\Taxonomy\RestNamespace;
use Pollora\Attributes\Taxonomy\Rewrite;
use Pollora\Attributes\Taxonomy\ShowAdminColumn;
use Pollora\Attributes\Taxonomy\ShowInNavMenus;
use Pollora\Attributes\Taxonomy\ShowInQuickEdit;
use Pollora\Attributes\Taxonomy\ShowInRest;
use Pollora\Attributes\Taxonomy\ShowTagcloud;
use Pollora\Attributes\Taxonomy\ShowUI;
use Pollora\Attributes\Taxonomy\Sort;
use Pollora\Attributes\Taxonomy\UpdateCountCallback;
use Pollora\Taxonomy\Contracts\Taxonomy;

// Classe de test qui implémente l'interface Taxonomy
#[ShowTagcloud]
#[ShowInQuickEdit]
#[ShowAdminColumn]
#[DefaultTerm('Default Term')]
#[Sort]
#[Args(['orderby' => 'name'])]
#[CheckedOntop]
#[Exclusive]
#[AllowHierarchy]
#[PublicTaxonomy]
#[Label('Test Taxonomy')]
#[Description('This is a test taxonomy description')]
#[ShowUI]
#[ShowInNavMenus]
#[QueryVar('test_query_var')]
#[Rewrite(['slug' => 'test-slug'])]
#[RestNamespace('test/v1')]
#[RestControllerClass('WP_REST_Terms_Controller')]
#[RestBase('test-terms')]
#[ShowInRest]
#[Hierarchical]
#[ObjectType(['post', 'page'])]
class TestTaxonomy implements Attributable, Taxonomy
{
    public array $attributeArgs = [];

    protected string $slug = 'test-taxonomy';

    protected array $objectType = ['post'];

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return 'Test Taxonomy';
    }

    public function getPluralName(): string
    {
        return 'Test Taxonomies';
    }

    public function getLabels(): array
    {
        return [
            'name' => 'Test Taxonomies',
            'singular_name' => 'Test Taxonomy',
        ];
    }

    public function getObjectType(): array|string
    {
        return $this->objectType;
    }

    #[MetaBoxCb]
    public function metaBoxCallback(): void
    {
        // Meta box callback implementation
    }

    #[MetaBoxSanitizeCb]
    public function sanitizeMetaBox($terms): array
    {
        return (array) $terms;
    }

    #[UpdateCountCallback]
    public function updateCount($terms, $taxonomy): void
    {
        // Update count implementation
    }

    public function withArgs(): array
    {
        return [
            'public' => true,
        ];
    }

    public function getArgs(): array
    {
        return array_merge(
            $this->attributeArgs,
            $this->withArgs(),
            [
                'labels' => $this->getLabels(),
            ]
        );
    }
}

beforeAll(function () {
    // Créer et configurer le container
    $app = new Container;
    Facade::setFacadeApplication($app);
});

afterAll(function () {
    m::close();
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
});

test('ShowTagcloud attribute sets show_tagcloud parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['show_tagcloud'])->toBeTrue();
});

test('ShowInQuickEdit attribute sets show_in_quick_edit parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['show_in_quick_edit'])->toBeTrue();
});

test('ShowAdminColumn attribute sets show_admin_column parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['show_admin_column'])->toBeTrue();
});

test('DefaultTerm attribute sets default_term parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['default_term'])->toBe('Default Term');
});

test('Sort attribute sets sort parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['sort'])->toBeTrue();
});

test('Args attribute sets args parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['args'])->toBe(['orderby' => 'name']);
});

test('CheckedOntop attribute sets checked_ontop parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['checked_ontop'])->toBeTrue();
});

test('Exclusive attribute sets exclusive parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['exclusive'])->toBeTrue();
});

test('AllowHierarchy attribute sets allow_hierarchy parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['allow_hierarchy'])->toBeTrue();
});

test('MetaBoxCb attribute sets meta_box_cb parameter', function () {
    $taxonomy = new TestTaxonomy;

    // Réinitialiser attributeArgs pour éviter les interférences
    $taxonomy->attributeArgs = [];

    // Récupérer les méthodes avec l'attribut MetaBoxCb
    $reflectionClass = new ReflectionClass($taxonomy);
    $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

    foreach ($methods as $method) {
        $attributes = $method->getAttributes(MetaBoxCb::class);
        foreach ($attributes as $attribute) {
            $attributeInstance = $attribute->newInstance();
            $attributeInstance->handle($taxonomy, $method, $attributeInstance);
        }
    }

    expect($taxonomy->attributeArgs['meta_box_cb'])->toBeArray()
        ->toHaveCount(2)
        ->toHaveKey(0)
        ->toHaveKey(1);

    expect($taxonomy->attributeArgs['meta_box_cb'][0])->toBe($taxonomy);
    expect($taxonomy->attributeArgs['meta_box_cb'][1])->toBe('metaBoxCallback');
});

test('MetaBoxSanitizeCb attribute sets meta_box_sanitize_cb parameter', function () {
    $taxonomy = new TestTaxonomy;

    // Réinitialiser attributeArgs pour éviter les interférences
    $taxonomy->attributeArgs = [];

    // Récupérer les méthodes avec l'attribut MetaBoxSanitizeCb
    $reflectionClass = new ReflectionClass($taxonomy);
    $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

    foreach ($methods as $method) {
        $attributes = $method->getAttributes(MetaBoxSanitizeCb::class);
        foreach ($attributes as $attribute) {
            $attributeInstance = $attribute->newInstance();
            $attributeInstance->handle($taxonomy, $method, $attributeInstance);
        }
    }

    expect($taxonomy->attributeArgs['meta_box_sanitize_cb'])->toBeArray()
        ->toHaveCount(2)
        ->toHaveKey(0)
        ->toHaveKey(1);

    expect($taxonomy->attributeArgs['meta_box_sanitize_cb'][0])->toBe($taxonomy);
    expect($taxonomy->attributeArgs['meta_box_sanitize_cb'][1])->toBe('sanitizeMetaBox');
});

test('UpdateCountCallback attribute sets update_count_callback parameter', function () {
    $taxonomy = new TestTaxonomy;

    // Réinitialiser attributeArgs pour éviter les interférences
    $taxonomy->attributeArgs = [];

    // Récupérer les méthodes avec l'attribut UpdateCountCallback
    $reflectionClass = new ReflectionClass($taxonomy);
    $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

    foreach ($methods as $method) {
        $attributes = $method->getAttributes(UpdateCountCallback::class);
        foreach ($attributes as $attribute) {
            $attributeInstance = $attribute->newInstance();
            $attributeInstance->handle($taxonomy, $method, $attributeInstance);
        }
    }

    expect($taxonomy->attributeArgs['update_count_callback'])->toBeArray()
        ->toHaveCount(2)
        ->toHaveKey(0)
        ->toHaveKey(1);

    expect($taxonomy->attributeArgs['update_count_callback'][0])->toBe($taxonomy);
    expect($taxonomy->attributeArgs['update_count_callback'][1])->toBe('updateCount');
});

test('ObjectType attribute sets object_type parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['object_type'])->toBe(['post', 'page']);
});

test('PublicTaxonomy attribute sets public parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['public'])->toBeTrue();
});

test('Label attribute sets label parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['label'])->toBe('Test Taxonomy');
});

test('Description attribute sets description parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['description'])->toBe('This is a test taxonomy description');
});

test('ShowUI attribute sets show_ui parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['show_ui'])->toBeTrue();
});

test('ShowInNavMenus attribute sets show_in_nav_menus parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['show_in_nav_menus'])->toBeTrue();
});

test('QueryVar attribute sets query_var parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['query_var'])->toBe('test_query_var');
});

test('Rewrite attribute sets rewrite parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['rewrite'])->toBe(['slug' => 'test-slug']);
});

test('RestNamespace attribute sets rest_namespace parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['rest_namespace'])->toBe('test/v1');
});

test('RestControllerClass attribute sets rest_controller_class parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['rest_controller_class'])->toBe('WP_REST_Terms_Controller');
});

test('RestBase attribute sets rest_base parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['rest_base'])->toBe('test-terms');
});

test('ShowInRest attribute sets show_in_rest parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['show_in_rest'])->toBeTrue();
});

test('Hierarchical attribute sets hierarchical parameter', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    expect($taxonomy->attributeArgs['hierarchical'])->toBeTrue();
});

test('getArgs method merges attribute args with withArgs and labels', function () {
    $taxonomy = new TestTaxonomy;
    AttributeProcessor::process($taxonomy);

    $args = $taxonomy->getArgs();

    expect($args)->toBeArray()
        ->toHaveKey('show_tagcloud')
        ->toHaveKey('show_in_quick_edit')
        ->toHaveKey('show_admin_column')
        ->toHaveKey('default_term')
        ->toHaveKey('sort')
        ->toHaveKey('args')
        ->toHaveKey('checked_ontop')
        ->toHaveKey('exclusive')
        ->toHaveKey('allow_hierarchy')
        ->toHaveKey('public')
        ->toHaveKey('label')
        ->toHaveKey('description')
        ->toHaveKey('show_ui')
        ->toHaveKey('show_in_nav_menus')
        ->toHaveKey('query_var')
        ->toHaveKey('rewrite')
        ->toHaveKey('rest_namespace')
        ->toHaveKey('rest_controller_class')
        ->toHaveKey('rest_base')
        ->toHaveKey('show_in_rest')
        ->toHaveKey('hierarchical')
        ->toHaveKey('labels');

    expect($args['public'])->toBeTrue();
    expect($args['labels'])->toBeArray()
        ->toHaveKey('name')
        ->toHaveKey('singular_name');
});
