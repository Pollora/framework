<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Pollora\Services\WordPress\Database\EnvironmentFileHandler;
use Pollora\Services\WordPress\Installation\SaltsService;
use Pollora\WordPress\Commands\SaltsGenerateCommand;

beforeEach(function (): void {
    $this->workingDirectory = getcwd();
    chdir(base_path());

    $this->envPath = base_path('.env');
    $this->hadEnv = File::exists($this->envPath);
    $this->previous = $this->hadEnv ? File::get($this->envPath) : null;

    config()->set('wordpress.constants', []);
    $this->salts = new SaltsService(new EnvironmentFileHandler);
    resolve(Kernel::class)->registerCommand(new SaltsGenerateCommand($this->salts));
});

afterEach(function (): void {
    chdir($this->workingDirectory);

    if ($this->hadEnv) {
        File::put($this->envPath, $this->previous);
    } else {
        File::delete($this->envPath);
    }
});

/** @return array<string, string> */
function envValues(string $path): array
{
    preg_match_all('/^([A-Z_]+)=(.*)$/m', File::get($path), $found);

    return array_combine($found[1], $found[2]);
}

it('says all eight are missing in a new project, and gives each its own value, once', function (): void {
    File::put($this->envPath, "APP_NAME=Pollora\n# AUTH_KEY=\nSECURE_AUTH_KEY=\n");

    expect($this->salts->missing())->toBe(SaltsService::KEYS);

    $this->salts->generate($this->salts->missing());
    $values = envValues($this->envPath);

    expect(array_intersect_key($values, array_flip(SaltsService::KEYS)))->toHaveCount(8)
        ->and(array_unique(array_intersect_key($values, array_flip(SaltsService::KEYS))))->toHaveCount(8)
        ->and($values['APP_NAME'])->toBe('Pollora')
        ->and($this->salts->missing())->toBe([]);

    foreach (SaltsService::KEYS as $key) {
        // Long, and nothing a .env file would read as something else
        expect($values[$key])->toMatch('/^[A-Za-z0-9_-]{64}$/D');
    }
});

it('keeps the keys that are there, whether they are in the file or given by the environment of the process', function (): void {
    File::put($this->envPath, 'AUTH_KEY="'.str_repeat('a', 64)."\"\nNONCE_SALT='".str_repeat('b', 40)."'\n");
    config()->set('wordpress.constants', ['logged_in_key' => str_repeat('c', 64)]);

    $missing = $this->salts->missing();
    $this->salts->generate($missing);

    expect($missing)->toBe(['SECURE_AUTH_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT'])
        ->and(File::get($this->envPath))->toContain('AUTH_KEY="'.str_repeat('a', 64).'"')->toContain("NONCE_SALT='".str_repeat('b', 40)."'")->not->toContain('LOGGED_IN_KEY=');
});

it('treats the sample value as no value', function (): void {
    File::put($this->envPath, "AUTH_KEY='put your unique phrase here'\n");

    expect($this->salts->missing())->toContain('AUTH_KEY');
});

it('makes no .env where there is none: a platform that gives variables to the process keeps them', function (): void {
    File::delete($this->envPath);

    expect($this->salts->canWrite())->toBeFalse();

    $this->artisan('pollora:salts:generate')->assertFailed();

    expect(File::exists($this->envPath))->toBeFalse();
});

it('is a command that writes what is missing, prints no value, and replaces everything only with --force', function (): void {
    File::put($this->envPath, "APP_NAME=Pollora\n");

    $this->artisan('pollora:salts:generate')->assertSuccessful();
    $first = envValues($this->envPath);
    $this->artisan('pollora:salts:generate')->assertSuccessful();

    expect(envValues($this->envPath))->toBe($first);

    $this->artisan('pollora:salts:generate', ['--force' => true])->assertSuccessful();

    expect(envValues($this->envPath)['AUTH_KEY'])->not->toBe($first['AUTH_KEY']);
});

it('counts a blank value and the sample written in capitals as no value', function (string $line): void {
    File::put($this->envPath, $line."\n");

    expect($this->salts->missing())->toContain('AUTH_KEY');
})->with(['quoted blanks' => ['AUTH_KEY="   "'], 'capitals' => ['AUTH_KEY="PUT YOUR UNIQUE PHRASE HERE"']]);

it('says where to put them when there is no .env, naming the eight', function (): void {
    File::delete($this->envPath);

    $this->artisan('pollora:salts:generate')
        ->expectsOutputToContain('There is no .env file here')
        ->expectsOutputToContain('  '.implode(', ', SaltsService::KEYS))
        ->assertFailed();
});

it('says that nothing is to be done, and writes nothing, when they are all set', function (): void {
    File::put($this->envPath, "APP_NAME=Pollora\n");
    $this->salts->generate(SaltsService::KEYS);
    $before = File::get($this->envPath);

    $this->artisan('pollora:salts:generate')
        ->expectsOutputToContain('already set')
        ->doesntExpectOutputToContain('written to .env')
        ->assertSuccessful();

    expect(File::get($this->envPath))->toBe($before);
});

it('says how many it wrote', function (): void {
    File::put($this->envPath, "APP_NAME=Pollora\n");

    $this->artisan('pollora:salts:generate')->expectsOutputToContain('8 key(s) and salt(s) written to .env.')->assertSuccessful();
});
