<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Pollora\WpCli\UI\Console\WpCliMakeCommand;

beforeEach(function (): void {
    $this->themesDir = sys_get_temp_dir().'/pollora-make-wp-cli-'.uniqid();
    mkdir($this->themesDir.'/test-theme', 0755, true);
    config(['theme.path' => $this->themesDir]);
    $this->app->make(Kernel::class)->registerCommand($this->app->make(WpCliMakeCommand::class));
    $this->generated = fn (): array => collect(File::allFiles($this->themesDir))
        ->mapWithKeys(fn ($file): array => [$file->getFilename() => (string) file_get_contents($file->getPathname())])
        ->all();
});

afterEach(function (): void {
    File::deleteDirectory($this->themesDir);
});

it('generates a command class carrying #[WpCli] and the description it was given', function (): void {
    $this->artisan('pollora:make:wp-cli', ['name' => 'SyncStock', '--theme' => 'test-theme', '--description' => 'Syncs the stock'])->assertSuccessful();

    $files = ($this->generated)();

    expect(array_keys($files))->toBe(['SyncStock.php'])
        ->and($files['SyncStock.php'])
        ->toContain('#[WpCli]')
        ->toContain('class SyncStock')
        ->toContain(' * Syncs the stock')
        ->toContain("WP_CLI::success('SyncStock executed successfully!');");
});

it('puts the class in the Cms\\Commands namespace of the theme', function (): void {
    $this->artisan('pollora:make:wp-cli', ['name' => 'SyncStock', '--theme' => 'test-theme'])->assertSuccessful();

    expect(($this->generated)()['SyncStock.php'])->toMatch('/^namespace [\w\\\\]+\\\\Cms\\\\Commands;$/m');
});

it('falls back to a generic description', function (): void {
    $this->artisan('pollora:make:wp-cli', ['name' => 'SyncStock', '--theme' => 'test-theme'])->assertSuccessful();

    expect(($this->generated)()['SyncStock.php'])->toContain(' * A custom WP CLI command');
});

it('refuses a command name that is not lowercase letters, numbers and hyphens', function (): void {
    $this->artisan('pollora:make:wp-cli', ['name' => 'SyncStock', '--theme' => 'test-theme', '--command' => 'Sync_Stock'])
        ->expectsOutputToContain('Command name must only contain lowercase letters, numbers, and hyphens.')
        ->run();

    expect(($this->generated)())->toBe([]);
});

it('accepts a class name ending in Command when no command name is given', function (): void {
    $this->artisan('pollora:make:wp-cli', ['name' => 'SyncStockCommand', '--theme' => 'test-theme'])->assertSuccessful();

    expect(array_keys(($this->generated)()))->toBe(['SyncStockCommand.php']);
});

it('does not overwrite an existing class unless forced', function (): void {
    $this->artisan('pollora:make:wp-cli', ['name' => 'SyncStock', '--theme' => 'test-theme', '--description' => 'First'])->assertSuccessful();

    $this->artisan('pollora:make:wp-cli', ['name' => 'SyncStock', '--theme' => 'test-theme', '--description' => 'Second'])
        ->expectsOutputToContain('WP CLI Command already exists.')
        ->run();
    expect(($this->generated)()['SyncStock.php'])->toContain(' * First');

    $this->artisan('pollora:make:wp-cli', ['name' => 'SyncStock', '--theme' => 'test-theme', '--description' => 'Second', '--force' => true])->assertSuccessful();
    expect(($this->generated)()['SyncStock.php'])->toContain(' * Second');
});
