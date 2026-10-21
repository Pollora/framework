<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Pollora\Services\WordPress\Database\EnvironmentFileHandler;

beforeEach(function (): void {
    // The handler looks for .env relative to the working directory, as Artisan runs from the project root.
    $this->workingDirectory = getcwd();
    chdir(base_path());

    $this->envPath = base_path('.env');
    $this->hadEnv = File::exists($this->envPath);
    $this->previous = $this->hadEnv ? File::get($this->envPath) : null;

    File::put($this->envPath, "APP_NAME=Pollora\nDB_USERNAME=root\nDB_PASSWORD=old\n");
});

afterEach(function (): void {
    chdir($this->workingDirectory);

    if ($this->hadEnv) {
        File::put($this->envPath, $this->previous);
    } else {
        File::delete($this->envPath);
    }
});

it('writes an empty database password as an empty value', function (): void {
    (new EnvironmentFileHandler)->updateEnvFile(['DB_PASSWORD' => '']);

    $lines = explode("\n", File::get($this->envPath));

    expect($lines)->toContain('DB_PASSWORD=')
        ->and($lines)->toContain('DB_USERNAME=root');
});
