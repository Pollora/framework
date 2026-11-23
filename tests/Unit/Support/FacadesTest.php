<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Hook\Domain\Contract\Action as ActionContract;
use Pollora\Hook\Domain\Contract\Filter as FilterContract;
use Pollora\Logging\Domain\Contracts\WordPressErrorReporterInterface;
use Pollora\Media\Application\Services\ImageSizes;
use Pollora\Option\Domain\Contracts\OptionManagerInterface;
use Pollora\PostType\Domain\Contracts\PostTypeFactoryInterface;
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
use Pollora\Taxonomy\Domain\Contracts\TaxonomyFactoryInterface;
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
    'Option' => [Option::class, OptionManagerInterface::class],
    'PostType' => [PostType::class, PostTypeFactoryInterface::class],
    'Role' => [Role::class, RoleDeclarer::class],
    'Taxonomy' => [Taxonomy::class, TaxonomyFactoryInterface::class],
    'Theme' => [Theme::class, ThemeService::class],
    'WordPressError' => [WordPressError::class, WordPressErrorReporterInterface::class],
    'WPHash' => [WPHash::class, 'wp.hash'],
]);

it('forwards Option::forget() to the option manager bound under its contract', function (): void {
    $this->container->instance(OptionManagerInterface::class, $this->service);

    expect(Option::forget('blogname'))->toBe('result of forget')
        ->and($this->service->calls)->toBe([['forget', ['blogname']]]);
});
