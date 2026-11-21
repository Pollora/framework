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

it('leaves out a variable the file does not define, by default', function (): void {
    (new EnvironmentFileHandler)->updateEnvFile(['DB_CONNECTION' => 'sqlite', 'DB_USERNAME' => 'admin']);

    $lines = explode("\n", File::get($this->envPath));

    expect($lines)->toContain('DB_USERNAME=admin')
        ->and(implode("\n", $lines))->not->toContain('DB_CONNECTION');
});

it('appends a variable the file does not define when asked to', function (): void {
    (new EnvironmentFileHandler)->updateEnvFile(['DB_CONNECTION' => 'sqlite', 'DB_USERNAME' => 'admin'], append: true);

    expect(File::get($this->envPath))->toBe("APP_NAME=Pollora\nDB_USERNAME=admin\nDB_PASSWORD=old\nDB_CONNECTION=sqlite\n");
});

it('does not append a variable twice, nor one whose name only prefixes another', function (): void {
    File::put($this->envPath, "DB_CONNECTION_EXTRA=x\nDB_CONNECTION=mysql");

    (new EnvironmentFileHandler)->updateEnvFile(['DB_CONNECTION' => 'sqlite'], append: true);

    expect(File::get($this->envPath))->toBe("DB_CONNECTION_EXTRA=x\nDB_CONNECTION=sqlite");
});

it('uncomments a variable that is only commented out, rather than appending it', function (): void {
    File::put($this->envPath, "# DB_CONNECTION=mysql\nAPP_NAME=x\n");

    (new EnvironmentFileHandler)->updateEnvFile(['DB_CONNECTION' => 'sqlite'], append: true);

    expect(File::get($this->envPath))->toBe("DB_CONNECTION=sqlite\nAPP_NAME=x\n");
});
