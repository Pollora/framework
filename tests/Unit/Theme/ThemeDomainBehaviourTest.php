<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Mockery as m;
use Pollora\Collection\Domain\Contracts\CollectionFactoryInterface;
use Pollora\Collection\Domain\Contracts\CollectionInterface;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Theme\Domain\Exceptions\ThemeSetupException;
use Pollora\Theme\Domain\Models\Menus;
use Pollora\Theme\Domain\Models\Templates;
use Pollora\Theme\Domain\Support\ThemeCollection;
use Pollora\Theme\Domain\Support\ThemeConfig;
use Pollora\Theme\Infrastructure\Adapters\DomainContainerAdapter;
use Pollora\Theme\Infrastructure\Services\Support;
use Psr\Container\ContainerInterface;

function themeBehaviourConfig(array $values): ConfigRepositoryInterface
{
    $config = m::mock(ConfigRepositoryInterface::class);
    $config->shouldReceive('get')->andReturnUsing(fn (string $key, $default = null) => $values[$key] ?? $default);
    ThemeConfig::setRepository($config);

    return $config;
}

function themeBehaviourContainer(array $extra = []): ContainerInterface
{
    $container = m::mock(ContainerInterface::class);
    $container->shouldReceive('get')->with(Action::class)->andReturn($extra[Action::class] ?? m::mock(Action::class));
    $container->shouldReceive('get')->with(Filter::class)->andReturn($extra[Filter::class] ?? m::mock(Filter::class));

    return $container;
}

afterEach(function (): void {
    ThemeConfig::reset();
});

describe('Menus hooks', function (): void {
    it('registers the menus action and the three nav menu attribute filters', function (): void {
        $action = m::mock(Action::class);
        $action->shouldReceive('add')->once()->with('after_setup_theme', m::type(Closure::class), 20);
        $filter = m::mock(Filter::class);
        $filter->shouldReceive('add')->once()->with('nav_menu_link_attributes', m::type(Closure::class), 10, 4);
        $filter->shouldReceive('add')->once()->with('nav_menu_item_attributes', m::type(Closure::class), 10, 4);
        $filter->shouldReceive('add')->once()->with('nav_menu_submenu_attributes', m::type(Closure::class), 10, 3);

        (new Menus(themeBehaviourContainer([Action::class => $action, Filter::class => $filter]), themeBehaviourConfig([])))->register();
    });
});

describe('Menus attribute rules', function (): void {
    beforeEach(function (): void {
        $this->menus = new Menus(themeBehaviourContainer(), themeBehaviourConfig([]));
    });

    it('applies a class rule on the link of a top-level item only', function (): void {
        $args = (object) ['link_config' => [['depth' => 0, 'class' => 'nav-link text-sm']]];
        $item = (object) ['menu_order' => 1];

        $applied = $this->menus->handleLinkAttributes(['href' => '/a'], $item, $args, 0);
        expect(explode(' ', trim($applied['class'])))->toBe(['nav-link', 'text-sm'])
            ->and($applied['href'])->toBe('/a');

        expect($this->menus->handleLinkAttributes(['href' => '/a'], $item, $args, 1))
            ->toBe(['class' => '', 'href' => '/a']);
    });

    it('appends rule classes after the existing ones, given as an array', function (): void {
        $args = (object) ['item_config' => [['depth' => 0, 'class' => ['a', 'b']]]];
        $item = (object) ['menu_order' => 3];

        expect($this->menus->handleItemAttributes(['class' => 'menu-item'], $item, $args, 0)['class'])->toBe('menu-item a b');
    });

    it('does not start the class with a space when the existing class is empty', function (): void {
        $args = (object) ['item_config' => [['depth' => 0, 'class' => 'foo']]];
        $item = (object) ['menu_order' => 1];

        expect($this->menus->handleItemAttributes(['class' => ''], $item, $args, 0)['class'])->toBe('foo')
            ->and($this->menus->handleItemAttributes([], $item, $args, 0)['class'])->toBe('foo');
    });

    it('restricts a rule to one menu order with eq', function (): void {
        $args = (object) ['item_config' => [['depth' => 0, 'eq' => 2, 'class' => 'second']]];

        expect($this->menus->handleItemAttributes([], (object) ['menu_order' => 2], $args, 0)['class'])->toContain('second')
            ->and($this->menus->handleItemAttributes([], (object) ['menu_order' => 3], $args, 0)['class'])->not->toContain('second');
    });

    it('merges extra attributes from a rule and lets them override', function (): void {
        $args = (object) ['submenu_config' => [['depth' => 1, 'attrs' => ['role' => 'menu', 'data-x' => '1']]]];

        expect($this->menus->handleSubmenuAttributes(['role' => 'none'], $args, 1))
            ->toBe(['class' => '', 'role' => 'menu', 'data-x' => '1']);
    });

    it('ignores a rule without a depth', function (): void {
        $args = (object) ['submenu_config' => [['class' => 'x']]];

        expect($this->menus->handleSubmenuAttributes([], $args, 0))->toBe(['class' => '']);
    });

    it('leaves attributes alone when the menu args carry no configuration', function (): void {
        expect($this->menus->handleSubmenuAttributes(['id' => 'm'], (object) [], 0))->toBe(['class' => '', 'id' => 'm']);
    });
});

describe('Templates', function (): void {
    it('hooks into theme_page_templates', function (): void {
        $action = m::mock(Action::class);
        $action->shouldReceive('add')->once()->with('theme_page_templates', m::type(Closure::class), 10, 3);

        (new Templates(themeBehaviourContainer([Action::class => $action]), themeBehaviourConfig([])))->register();
    });

    it('adds the configured templates that target the post type', function (): void {
        $templates = new Templates(themeBehaviourContainer(), themeBehaviourConfig(['theme.templates' => [
            'tpl-landing' => ['label' => 'Landing', 'post_types' => ['page']],
            'tpl-news' => ['label' => 'News', 'post_types' => ['post', 'page']],
            'tpl-product' => ['label' => 'Product', 'post_types' => 'product'],
            'tpl-orphan' => ['label' => 'No types'],
        ]]));

        expect($templates->getThemePageTemplates('page'))->toBe(['tpl-landing' => 'Landing', 'tpl-news' => 'News'])
            ->and($templates->getThemePageTemplates('product'))->toBe(['tpl-product' => 'Product'])
            ->and($templates->getThemePageTemplates('other'))->toBe([]);
    });

    it('merges the theme templates into the WordPress list for a post', function (): void {
        $templates = new Templates(themeBehaviourContainer(), themeBehaviourConfig(['theme.templates' => [
            'tpl-a' => ['label' => 'A', 'post_types' => ['page']],
        ]]));

        expect($templates->registerTemplates(['existing.php' => 'Existing'], null, (object) ['post_type' => 'page']))
            ->toBe(['existing.php' => 'Existing', 'tpl-a' => 'A']);
    });

    it('returns the WordPress list untouched when there is no post', function (): void {
        $templates = new Templates(themeBehaviourContainer(), themeBehaviourConfig([]));

        expect($templates->registerTemplates(['existing.php' => 'Existing'], null, null))->toBe(['existing.php' => 'Existing']);
    });
});

describe('Support', function (): void {
    it('hooks add_theme_support on after_setup_theme at priority 1', function (): void {
        $action = m::mock(Action::class);
        $action->shouldReceive('add')->once()->with('after_setup_theme', m::type(Closure::class), 1);

        (new Support(themeBehaviourContainer([Action::class => $action]), themeBehaviourConfig([])))->register();
    });

    it('passes keyed supports with their value and bare ones alone', function (): void {
        $support = new Support(themeBehaviourContainer(), themeBehaviourConfig(['theme.supports' => [
            'post-thumbnails' => ['post', 'page'],
            'title-tag',
        ]]));
        $calls = [];
        Functions\when('add_theme_support')->alias(function (...$args) use (&$calls): bool {
            $calls[] = $args;

            return true;
        });

        $support->addThemeSupport();

        expect($calls)->toBe([['post-thumbnails', ['post', 'page']], ['title-tag']]);
    });
});

describe('ThemeConfig and ThemeCollection', function (): void {
    it('prefixes keys with theme. and reports its initialisation', function (): void {
        ThemeConfig::reset();
        expect(ThemeConfig::isInitialized())->toBeFalse();

        themeBehaviourConfig(['theme.menus' => ['a' => 'A']]);

        expect(ThemeConfig::isInitialized())->toBeTrue()
            ->and(ThemeConfig::get('menus'))->toBe(['a' => 'A'])
            ->and(ThemeConfig::get('missing', 'fallback'))->toBe('fallback');
    });

    it('returns the default when the repository is missing but one is given', function (): void {
        ThemeConfig::reset();

        expect(ThemeConfig::get('menus', []))->toBe([])->and(ThemeConfig::get('x', null))->toBeNull();
    });

    it('throws when the repository is missing and no default is given', function (): void {
        ThemeConfig::reset();

        ThemeConfig::get('menus');
    })->throws(ThemeSetupException::class, 'ConfigRepository not initialized');

    it('builds collections through the injected factory', function (): void {
        $collection = m::mock(CollectionInterface::class);
        $factory = m::mock(CollectionFactoryInterface::class);
        $factory->shouldReceive('make')->once()->with([1, 2])->andReturn($collection);
        ThemeCollection::setFactory($factory);

        try {
            expect(ThemeCollection::make([1, 2]))->toBe($collection);
        } finally {
            (new ReflectionProperty(ThemeCollection::class, 'collectionFactory'))->setValue(null, null);
        }
    });

    it('throws when no collection factory was set', function (): void {
        (new ReflectionProperty(ThemeCollection::class, 'collectionFactory'))->setValue(null, null);

        ThemeCollection::make([1]);
    })->throws(ThemeSetupException::class, 'CollectionFactory not initialized');
});

describe('DomainContainerAdapter', function (): void {
    it('maps the domain container port onto the Laravel container', function (): void {
        $app = new Container;
        $app->instance('config', new Repository(['a' => 1]));
        $app->bind('thing', fn (): stdClass => new stdClass);

        $adapter = new DomainContainerAdapter($app);

        expect($adapter->has('thing'))->toBeTrue()
            ->and($adapter->has('nothing'))->toBeFalse()
            ->and($adapter->get('thing'))->toBeInstanceOf(stdClass::class)
            ->and($adapter->isConfigurationCached())->toBeFalse()
            ->and($adapter->getConfig('a'))->toBe(1)
            ->and($adapter->getConfig('zz', 'd'))->toBe('d');

        $adapter->setConfig('b', 2);
        expect($app['config']->get('b'))->toBe(2);
    });

    it('registers providers and shared bindings on the application', function (): void {
        $app = m::mock();
        $app->shouldReceive('register')->once()->with('Provider');
        $app->shouldReceive('singleton')->once()->with('abstract', 'concrete');
        $adapter = new DomainContainerAdapter($app);

        $adapter->registerProvider('Provider');
        $adapter->bindShared('abstract', 'concrete');
    });
});
