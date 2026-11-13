<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Hook\Domain\Contract\Action as ActionContract;
use Pollora\Hook\Domain\Contract\Filter as FilterContract;
use Pollora\Logging\Application\Services\WordPressErrorLoggingService;
use Pollora\Media\Application\Services\ImageSizes;
use Pollora\Option\Application\Service\OptionService;
use Pollora\PostType\Infrastructure\Factories\PostTypeFactory;
use Pollora\Role\Application\Services\RoleDeclarer;
use Pollora\Support\Facades\Ability;
use Pollora\Support\Facades\Action;
use Pollora\Support\Facades\Ajax;
use Pollora\Support\Facades\Asset;
use Pollora\Support\Facades\Constant;
use Pollora\Support\Facades\Filter;
use Pollora\Support\Facades\ImageSize;
use Pollora\Support\Facades\Mail;
use Pollora\Support\Facades\Meta;
use Pollora\Support\Facades\Option;
use Pollora\Support\Facades\PostType;
use Pollora\Support\Facades\Role;
use Pollora\Support\Facades\Taxonomy;
use Pollora\Support\Facades\Theme;
use Pollora\Support\Facades\WordPressError;
use Pollora\Support\Facades\WPHash;
use Pollora\Taxonomy\Infrastructure\Factories\TaxonomyFactory;
use Pollora\Theme\Domain\Contracts\ThemeService;

beforeEach(function (): void {
    $this->container = new Container;

    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($this->container);

    // Stands in for the real service: records the calls it receives.
    $this->service = new class
    {
        /** @var array<int, array{0: string, 1: array<int, mixed>}> */
        public array $calls = [];

        public function __call(string $method, array $arguments): string
        {
            $this->calls[] = [$method, $arguments];

            return 'result of '.$method;
        }
    };
});

afterEach(function (): void {
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
});

it('forwards the facade calls to the service bound under its accessor', function (string $facade, string $accessor): void {
    $this->container->instance($accessor, $this->service);

    expect($facade::getFacadeRoot())->toBe($this->service)
        ->and($facade::someMethod('a', 2))->toBe('result of someMethod')
        ->and($this->service->calls)->toBe([['someMethod', ['a', 2]]]);
})->with([
    'Ability' => [Ability::class, 'wp.abilities'],
    'Action' => [Action::class, ActionContract::class],
    'Ajax' => [Ajax::class, 'wp.ajax'],
    'Asset' => [Asset::class, AssetManager::class],
    'Constant' => [Constant::class, 'constant.manager'],
    'Filter' => [Filter::class, FilterContract::class],
    'ImageSize' => [ImageSize::class, ImageSizes::class],
    'Mail' => [Mail::class, 'wp.mail'],
    'Meta' => [Meta::class, 'wp.meta'],
    'Option' => [Option::class, OptionService::class],
    'PostType' => [PostType::class, PostTypeFactory::class],
    'Role' => [Role::class, RoleDeclarer::class],
    'Taxonomy' => [Taxonomy::class, TaxonomyFactory::class],
    'Theme' => [Theme::class, ThemeService::class],
    'WordPressError' => [WordPressError::class, WordPressErrorLoggingService::class],
    'WPHash' => [WPHash::class, 'wp.hash'],
]);

it('lets Option::forget() delete the option and report the result', function (bool $deleted): void {
    $service = new class($deleted)
    {
        /** @var array<int, string> */
        public array $deleted = [];

        public function __construct(private readonly bool $result) {}

        public function delete(string $key): bool
        {
            $this->deleted[] = $key;

            return $this->result;
        }
    };
    $this->container->instance(OptionService::class, $service);

    expect(Option::forget('blogname'))->toBe($deleted)
        ->and($service->deleted)->toBe(['blogname']);
})->with([true, false]);
