<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Pollora\Abilities\Adapter\Out\WordPress\WordPressAbilityCategoryRegistrar;
use Pollora\Abilities\Adapter\Out\WordPress\WordPressAbilityRegistrar;
use Pollora\Abilities\Application\Service\RegisterAbilityService;
use Pollora\Abilities\Domain\Contracts\AbilityHandler;
use Pollora\Abilities\Domain\Model\Ability;
use Pollora\Abilities\Domain\Model\AbilityCategory;
use Pollora\Abilities\Domain\Model\Input;
use Pollora\Abilities\Domain\Schema\SchemaBuilder;
use Pollora\Abilities\Factory\AbilityFactory;
use Pollora\Abilities\Port\Out\AbilityCategoryRegistrarPort;
use Pollora\Abilities\Port\Out\AbilityRegistrarPort;
use Pollora\Ability\Infrastructure\Providers\AbilityServiceProvider;
use Pollora\Ability\Infrastructure\Services\AbilityDiscovery;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\HookInterface;

beforeEach(function (): void {
    $this->previousContainer = Container::getInstance();
});

afterEach(function (): void {
    // Building an Application replaces the global container: put the previous one back.
    Container::setInstance($this->previousContainer);
});

function abilityProviderApp(): Application
{
    $app = new Application(sys_get_temp_dir());
    (new AbilityServiceProvider($app))->register();

    return $app;
}

it('binds both registrar ports to the WordPress registrars', function (): void {
    $app = abilityProviderApp();

    expect($app->make(AbilityRegistrarPort::class))->toBeInstanceOf(WordPressAbilityRegistrar::class)
        ->and($app->make(AbilityCategoryRegistrarPort::class))->toBeInstanceOf(WordPressAbilityCategoryRegistrar::class);
});

it('exposes the factory as wp.abilities, built on the shared registration service', function (): void {
    $app = abilityProviderApp();

    expect($app->make('wp.abilities'))->toBeInstanceOf(AbilityFactory::class)
        ->and($app->make('wp.abilities'))->toBe($app->make('wp.abilities'))
        ->and($app->make('wp.abilities')->service())->toBe($app->make(RegisterAbilityService::class));
});

it('builds the discovery on the wp.abilities factory', function (): void {
    $app = abilityProviderApp();

    expect($app->make(AbilityDiscovery::class))->toBeInstanceOf(AbilityDiscovery::class)
        ->and($app->make(AbilityDiscovery::class))->toBe($app->make(AbilityDiscovery::class));
});

it('publishes categories and abilities each on the hook WordPress accepts them on', function (): void {
    $app = abilityProviderApp();

    $hooks = [];
    $action = Mockery::mock(Action::class);
    $action->shouldReceive('add')->andReturnUsing(function (string $hook, callable $callback) use (&$hooks): HookInterface {
        $hooks[$hook] = $callback;

        return Mockery::mock(HookInterface::class);
    });
    $app->instance(Action::class, $action);

    // Replace the WordPress registrars so that publishing is observable without WordPress.
    $publishedCategories = [];
    $app->instance(AbilityCategoryRegistrarPort::class, new class($publishedCategories) implements AbilityCategoryRegistrarPort
    {
        public function __construct(private array &$published) {}

        public function register(AbilityCategory $category): bool
        {
            $this->published[] = $category->slug;

            return true;
        }

        public function exists(string $slug): bool
        {
            return false;
        }
    });
    $publishedAbilities = [];
    $app->instance(AbilityRegistrarPort::class, new class($publishedAbilities) implements AbilityRegistrarPort
    {
        public function __construct(private array &$published) {}

        public function register(Ability $ability): bool
        {
            $this->published[] = $ability->name;

            return true;
        }

        public function exists(string $name): bool
        {
            return false;
        }
    });
    $app->forgetInstance(RegisterAbilityService::class);

    $handler = new class implements AbilityHandler
    {
        public function schema(SchemaBuilder $schema): void {}

        public function authorize(Input $input): mixed
        {
            return true;
        }

        public function handle(Input $input): mixed
        {
            return [];
        }
    };
    $app->make('wp.abilities')->category('fixture-things', 'Things');
    $app->make('wp.abilities')->handle('fixture/do-things', $handler, 'fixture-things', 'Do things', 'Does things');

    (new AbilityServiceProvider($app))->boot();

    expect(array_keys($hooks))->toBe(['wp_abilities_api_categories_init', 'wp_abilities_api_init'])
        ->and($publishedCategories)->toBe([])
        ->and($publishedAbilities)->toBe([]);

    $hooks['wp_abilities_api_categories_init']();

    expect($publishedCategories)->toBe(['fixture-things'])
        ->and($publishedAbilities)->toBe([]);

    $hooks['wp_abilities_api_init']();

    expect($publishedAbilities)->toBe(['fixture/do-things']);
});
