<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Pollora\Hook\UI\Console\ActionMakeCommand;

beforeEach(function (): void {
    $this->themesDir = sys_get_temp_dir().'/pollora-make-action-'.uniqid();
    mkdir($this->themesDir.'/test-theme', 0755, true);
    config(['theme.path' => $this->themesDir]);
    $this->app->make(Kernel::class)->registerCommand($this->app->make(ActionMakeCommand::class));
    $this->generated = fn (): string => (string) file_get_contents((string) collect(File::allFiles($this->themesDir))->first()?->getPathname());
});

afterEach(function (): void {
    File::deleteDirectory($this->themesDir);
});

it('generates an #[Async] method with --async', function (): void {
    $this->artisan('pollora:make:action', ['name' => 'SyncToCrm', '--theme' => 'test-theme', '--hook' => 'save_post', '--async' => true])->assertSuccessful();

    expect(($this->generated)())
        ->toContain("use Pollora\\Attributes\\Action;\nuse Pollora\\Attributes\\Async;\n")
        ->toContain("    #[Action('save_post', priority:10)]\n    #[Async]\n    public function handleSavePost(): void");
});

it('generates a synchronous method without --async', function (): void {
    $this->artisan('pollora:make:action', ['name' => 'SyncToCrm', '--theme' => 'test-theme', '--hook' => 'save_post'])->assertSuccessful();

    expect(($this->generated)())->not->toContain('Async');
});

it('adds an #[Async] method, and its import, to an existing class', function (): void {
    $this->artisan('pollora:make:action', ['name' => 'SyncToCrm', '--theme' => 'test-theme', '--hook' => 'save_post'])->assertSuccessful();
    $this->artisan('pollora:make:action', ['name' => 'SyncToCrm', '--theme' => 'test-theme', '--hook' => 'user_register', '--async' => true])->assertSuccessful();

    expect(($this->generated)())
        ->toContain('use Pollora\\Attributes\\Async;')
        ->toContain("    #[Action('save_post', priority:10)]\n    public function handleSavePost(): void")
        ->toContain("    #[Action('user_register', priority:10)]\n    #[Async]\n    public function handleUserRegister(): void");
});
