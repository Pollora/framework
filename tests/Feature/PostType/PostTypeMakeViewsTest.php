<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Pollora\PostType\UI\Console\PostTypeMakeCommand;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;

/**
 * --single-view, --archive-view and --with-views write the template hierarchy
 * views of the new post type in the theme, named after the slug discovery
 * registers the post type under.
 */
beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/pollora-make-post-type-'.uniqid();
    mkdir($this->root.'/themes/test-theme', 0755, true);
    mkdir($this->root.'/themes/other-theme', 0755, true);
    mkdir($this->root.'/app', 0755, true);
    config(['theme.path' => $this->root.'/themes']);

    // A class generated without --theme goes to app_path(): keep it out of the
    // Testbench skeleton. The namespace is read from the skeleton first, as it
    // is detected from the app path.
    $this->app->getNamespace();
    $this->app->useAppPath($this->root.'/app');

    $this->activeTheme = function (?string $name): void {
        $theme = null;

        if ($name !== null) {
            $theme = Mockery::mock(ThemeModuleInterface::class);
            $theme->allows('getName')->andReturn($name);
        }

        $registrar = Mockery::mock(ThemeRegistrarInterface::class);
        $registrar->allows('getActiveTheme')->andReturn($theme);
        $this->app->instance(ThemeRegistrarInterface::class, $registrar);
    };
    ($this->activeTheme)('test-theme');

    $this->viewsDir = fn (string $theme = 'test-theme'): string => $this->root.'/themes/'.$theme.'/resources/views';
    $this->views = fn (string $theme = 'test-theme'): array => array_map(basename(...), glob(($this->viewsDir)($theme).'/*.blade.php') ?: []);

    $this->app->make(Kernel::class)->registerCommand($this->app->make(PostTypeMakeCommand::class));
});

afterEach(function (): void {
    File::deleteDirectory($this->root);
});

it('creates no view without a view option', function (): void {
    $this->artisan('pollora:make:post-type', ['name' => 'Car', '--theme' => 'test-theme'])->assertSuccessful();

    expect($this->root.'/themes/test-theme/app/Cms/PostTypes/Car.php')->toBeFile()
        ->and(($this->views)())->toBe([]);
});

it('creates the single view with --single-view', function (): void {
    $this->artisan('pollora:make:post-type', ['name' => 'Car', '--theme' => 'test-theme', '--single-view' => true])->assertSuccessful();

    expect(($this->views)())->toBe(['single-car.blade.php']);
});

it('creates the archive view with --archive-view', function (): void {
    $this->artisan('pollora:make:post-type', ['name' => 'Car', '--theme' => 'test-theme', '--archive-view' => true])->assertSuccessful();

    expect(($this->views)())->toBe(['archive-car.blade.php']);
});

it('creates both views with --with-views', function (): void {
    $this->artisan('pollora:make:post-type', ['name' => 'Car', '--theme' => 'test-theme', '--with-views' => true])->assertSuccessful();

    expect(($this->views)())->toBe(['archive-car.blade.php', 'single-car.blade.php']);
});

it('names the views after the kebab-case slug of a multi-word name', function (): void {
    $this->artisan('pollora:make:post-type', ['name' => 'SportsCar', '--theme' => 'test-theme', '--with-views' => true])->assertSuccessful();

    expect(($this->views)())->toBe(['archive-sports-car.blade.php', 'single-sports-car.blade.php']);
});

it('cuts the slug of a long name to the 20 characters WordPress registers', function (): void {
    $this->artisan('pollora:make:post-type', ['name' => 'ConferenceRegistrationForm', '--theme' => 'test-theme', '--single-view' => true])->assertSuccessful();

    expect(($this->views)())->toBe(['single-conference-registrat.blade.php']);
});

it('leaves an existing view untouched and still creates the other one', function (): void {
    mkdir(($this->viewsDir)(), 0755, true);
    file_put_contents(($this->viewsDir)().'/single-car.blade.php', 'My own single view');

    $this->artisan('pollora:make:post-type', ['name' => 'Car', '--theme' => 'test-theme', '--with-views' => true])
        ->expectsOutputToContain('already exists.')
        ->assertSuccessful();

    expect(file_get_contents(($this->viewsDir)().'/single-car.blade.php'))->toBe('My own single view')
        ->and(($this->views)())->toBe(['archive-car.blade.php', 'single-car.blade.php']);
});

it('writes the views to the active theme when the class goes to app/', function (): void {
    $this->artisan('pollora:make:post-type', ['name' => 'Car', '--with-views' => true])->assertSuccessful();

    expect($this->root.'/app/Cms/PostTypes/Car.php')->toBeFile()
        ->and(($this->views)())->toBe(['archive-car.blade.php', 'single-car.blade.php']);
});

it('still creates the class, and no view, when no theme is active', function (): void {
    ($this->activeTheme)(null);

    $this->artisan('pollora:make:post-type', ['name' => 'Car', '--with-views' => true])
        ->expectsOutputToContain('No active theme found, views not created.')
        ->assertSuccessful();

    expect($this->root.'/app/Cms/PostTypes/Car.php')->toBeFile()
        ->and(($this->views)('test-theme'))->toBe([])
        ->and(($this->views)('other-theme'))->toBe([]);
});

it('writes the views to the theme given with --theme rather than the active one', function (): void {
    $this->artisan('pollora:make:post-type', ['name' => 'Car', '--theme' => 'other-theme', '--with-views' => true])->assertSuccessful();

    expect(($this->views)('other-theme'))->toBe(['archive-car.blade.php', 'single-car.blade.php'])
        ->and(($this->views)('test-theme'))->toBe([]);
});

it('creates no view when the class already exists', function (): void {
    $this->artisan('pollora:make:post-type', ['name' => 'Car', '--theme' => 'test-theme'])->assertSuccessful();

    $this->artisan('pollora:make:post-type', ['name' => 'Car', '--theme' => 'test-theme', '--with-views' => true])
        ->expectsOutputToContain('PostType already exists.');

    expect(($this->views)())->toBe([]);
});

it('writes each view as its stub', function (string $template): void {
    $this->artisan('pollora:make:post-type', ['name' => 'Car', '--theme' => 'test-theme', '--with-views' => true])->assertSuccessful();

    $stub = dirname((string) (new ReflectionClass(PostTypeMakeCommand::class))->getFileName()).'/stubs/'.$template.'.blade.php.stub';

    expect(file_get_contents(($this->viewsDir)().'/'.$template.'-car.blade.php'))->toBe(file_get_contents($stub));
})->with(['single', 'archive']);
