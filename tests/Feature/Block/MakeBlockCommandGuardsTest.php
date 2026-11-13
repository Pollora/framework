<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Pollora\Block\UI\Console\MakeBlockCommand;

beforeEach(function (): void {
    $this->themesDir = sys_get_temp_dir().'/pollora-make-block-guards-'.uniqid();
    $this->themeDir = $this->themesDir.'/test-theme';
    mkdir($this->themeDir.'/resources/assets', 0755, true);

    file_put_contents($this->themeDir.'/vite.config.js', "import laravel from 'laravel-vite-plugin';\nexport default { plugins: [laravel({ input: ['./resources/assets/app.js'], refresh: [] })] };\n");
    file_put_contents($this->themeDir.'/package.json', json_encode(['name' => 'test-theme', 'devDependencies' => ['vite' => '^7.0.0']]));

    config(['theme.path' => $this->themesDir]);
    $this->app->make(Kernel::class)->registerCommand($this->app->make(MakeBlockCommand::class));
});

afterEach(function (): void {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->themesDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }

    rmdir($this->themesDir);
});

describe('pollora:make:block guards', function (): void {
    it('refuses a name that is not kebab-case and writes nothing', function (string $name): void {
        $this->artisan('pollora:make:block', ['name' => $name, '--theme' => 'test-theme'])
            ->expectsOutputToContain(sprintf('Invalid block name "%s"', $name))
            ->assertFailed();

        expect($this->themeDir.'/resources/views/blocks')->not->toBeDirectory();
    })->with(['capitals' => ['HeroBanner'], 'underscore' => ['hero_banner'], 'leading digit' => ['1hero'], 'leading dash' => ['-hero']]);

    it('refuses a theme that has no directory', function (): void {
        $this->artisan('pollora:make:block', ['name' => 'hero', '--theme' => 'ghost-theme'])
            ->expectsOutputToContain('Theme directory not found')
            ->assertFailed();
    });

    it('does not patch a vite.config.js that does not look like the Pollora one, and explains what to add', function (): void {
        file_put_contents($this->themeDir.'/vite.config.js', "export default { plugins: [] };\n");

        $this->artisan('pollora:make:block', ['name' => 'hero', '--theme' => 'test-theme'])
            ->expectsOutputToContain('does not match expected Pollora pattern')
            ->expectsOutputToContain('Add the following to your vite.config.js')
            ->assertSuccessful();

        expect((string) file_get_contents($this->themeDir.'/vite.config.js'))->toBe("export default { plugins: [] };\n")
            ->and($this->themeDir.'/resources/views/blocks/hero/block.json')->toBeFile();
    });

    it('tells that blocks are turned off in a vite.config.js built on @pollora/vite-config', function (): void {
        file_put_contents($this->themeDir.'/vite.config.js', "import { defineConfig } from '@pollora/vite-config';\nexport default defineConfig({ blocks: false });\n");

        $this->artisan('pollora:make:block', ['name' => 'hero', '--theme' => 'test-theme'])
            ->expectsOutputToContain('passes `blocks: false` to @pollora/vite-config')
            ->assertSuccessful();
    });

    it('says package.json is up to date when the dependencies are already there', function (): void {
        $this->artisan('pollora:make:block', ['name' => 'hero', '--theme' => 'test-theme'])->assertSuccessful();

        $package = json_decode((string) file_get_contents($this->themeDir.'/package.json'), true);

        expect(array_keys($package['devDependencies']))->toContain('@roots/vite-plugin');

        // A second first-block bootstrap: remove the block, keep the dependencies.
        $blocks = $this->themeDir.'/resources/views/blocks';
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($blocks, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($blocks);

        $this->artisan('pollora:make:block', ['name' => 'card', '--theme' => 'test-theme'])
            ->expectsOutputToContain('ALREADY UP TO DATE')
            ->assertSuccessful();
    });

    it('overwrites an existing block without asking when --force is given', function (): void {
        $this->artisan('pollora:make:block', ['name' => 'hero', '--theme' => 'test-theme', '--title' => 'First'])->assertSuccessful();

        $this->artisan('pollora:make:block', ['name' => 'hero', '--theme' => 'test-theme', '--title' => 'Second', '--force' => true])->assertSuccessful();

        $metadata = json_decode((string) file_get_contents($this->themeDir.'/resources/views/blocks/hero/block.json'), true);

        expect($metadata['title'])->toBe('Second');
    });

    it('keeps an existing block when the overwrite is declined', function (): void {
        $this->artisan('pollora:make:block', ['name' => 'hero', '--theme' => 'test-theme', '--title' => 'First'])->assertSuccessful();

        $this->artisan('pollora:make:block', ['name' => 'hero', '--theme' => 'test-theme', '--title' => 'Second'])
            ->expectsConfirmation('Block "hero" already exists. Overwrite?', 'no')
            ->assertFailed();

        $metadata = json_decode((string) file_get_contents($this->themeDir.'/resources/views/blocks/hero/block.json'), true);

        expect($metadata['title'])->toBe('First');
    });
});
