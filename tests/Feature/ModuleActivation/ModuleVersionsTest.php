<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Composer\InstalledVersions;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Pollora\Modules\Application\Services\ModuleVersions;
use Pollora\Modules\UI\Console\ModuleOutdatedCommand;
use Pollora\Modules\UI\Http\ModuleVersionsHealthCheck;
use Pollora\VersionCheck\Application\Services\PackageVersionChecker;
use Pollora\VersionCheck\Domain\Services\StableVersions;
use Pollora\VersionCheck\Infrastructure\Sources\ComposerRepositorySource;
use Pollora\VersionCheck\Infrastructure\Sources\GitHubSource;
use Pollora\VersionCheck\Infrastructure\Sources\HttpGet;
use Pollora\VersionCheck\Infrastructure\Sources\VersionSources;

/**
 * Answer WordPress HTTP requests from a map of URL => JSON body, recording each request.
 *
 * @param  array<string, mixed>  $responses
 */
function fakeHttp(array $responses, array &$requests = []): void
{
    Functions\when('wp_remote_get')->alias(function (string $url, array $args = []) use ($responses, &$requests): array {
        $requests[] = ['url' => $url, 'headers' => $args['headers'] ?? []];

        return array_key_exists($url, $responses)
            ? ['code' => 200, 'body' => (string) json_encode($responses[$url])]
            : ['code' => 404, 'body' => '{}'];
    });
    Functions\when('is_wp_error')->justReturn(false);
    Functions\when('wp_remote_retrieve_response_code')->alias(fn (array $response): int => $response['code']);
    Functions\when('wp_remote_retrieve_body')->alias(fn (array $response): string => $response['body']);
}

/**
 * Keep transients in memory.
 */
function fakeTransients(array &$transients): void
{
    // An arrow function would capture the array by value, and never see a later write
    Functions\when('get_transient')->alias(function (string $key) use (&$transients): mixed {
        return $transients[$key]['value'] ?? false;
    });
    Functions\when('set_transient')->alias(function (string $key, mixed $value, int $ttl) use (&$transients): bool {
        $transients[$key] = ['value' => $value, 'ttl' => $ttl];

        return true;
    });
}

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().'/pollora-module-versions-'.uniqid();
    File::ensureDirectoryExists($this->directory);
    $this->transients = [];
    fakeTransients($this->transients);
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

describe('StableVersions', function (): void {
    it('picks the highest stable version, whatever the order and the "v"', function (): void {
        expect(StableVersions::highest(['v1.2.0', '1.10.0', '2.0.0-beta1', '1.9.9', 'dev-main', '1.11.0-RC1']))->toBe('1.10.0')
            ->and(StableVersions::highest(['dev-main']))->toBeNull()
            ->and(StableVersions::isDevelopmentBuild('dev-main'))->toBeTrue()
            ->and(StableVersions::isDevelopmentBuild('1.x-dev'))->toBeTrue()
            ->and(StableVersions::isDevelopmentBuild('1.3.0'))->toBeFalse();
    });
});

describe('version sources', function (): void {
    it("reads a Composer repository's p2 metadata, Satis and Private Packagist included", function (): void {
        fakeHttp(['https://satis.example.com/p2/acme/crm.json' => ['packages' => ['acme/crm' => [
            ['version' => '1.5.0-beta1'], ['version' => 'v1.4.0'], ['version' => '1.3.0'],
        ]]]]);

        expect((new ComposerRepositorySource(new HttpGet, 'https://satis.example.com/'))->latest('acme/crm'))->toBe('1.4.0')
            ->and((new ComposerRepositorySource(new HttpGet, 'https://satis.example.com'))->latest('acme/other'))->toBeNull();
    });

    it("reads a GitHub repository's latest release, else its highest tag, with a token for a private one", function (): void {
        $requests = [];
        fakeHttp([
            'https://api.github.com/repos/acme/crm-module/tags?per_page=100' => [['name' => 'v2.1.0'], ['name' => 'v2.0.0'], ['name' => 'v3.0.0-alpha']],
        ], $requests);

        expect((new GitHubSource(new HttpGet, 'acme/crm-module', 'secret'))->latest('acme/crm-module'))->toBe('2.1.0')
            ->and($requests[0]['headers']['Authorization'])->toBe('Bearer secret');
    });

    it("picks the source from the project's repositories", function (): void {
        file_put_contents($this->directory.'/composer.json', json_encode(['repositories' => [
            ['type' => 'composer', 'url' => 'https://satis.example.com', 'only' => ['acme/*']],
            ['type' => 'vcs', 'url' => 'https://github.com/other/billing.git'],
        ]]));
        $sources = new VersionSources(new HttpGet, $this->directory.'/composer.json');

        expect($sources->for('acme/crm'))->toBeInstanceOf(ComposerRepositorySource::class)
            ->and($sources->for('other/billing'))->toBeInstanceOf(GitHubSource::class)
            ->and($sources->for('vendor/package')->releaseUrl('vendor/package', '1.0.0'))->toBe('https://packagist.org/packages/vendor/package#1.0.0');
    });
});

describe('PackageVersionChecker', function (): void {
    it('fetches only on refresh, and caches a missing answer for an hour', function (): void {
        $requests = [];
        fakeHttp(['https://repo.packagist.org/p2/acme/crm.json' => ['packages' => ['acme/crm' => [['version' => '1.4.0']]]]], $requests);
        $checker = new PackageVersionChecker(new VersionSources(new HttpGet, $this->directory.'/composer.json'));

        expect($checker->cachedLatest('acme/crm'))->toBeNull()
            ->and($requests)->toBe([])
            ->and($checker->refresh('acme/crm'))->toBe('1.4.0')
            ->and($checker->cachedLatest('acme/crm'))->toBe('1.4.0')
            ->and($this->transients['pollora_latest_'.sha1('acme/crm')]['ttl'])->toBe(PackageVersionChecker::TTL);

        $checker->refresh('acme/gone');

        expect($this->transients['pollora_latest_'.sha1('acme/gone')]['ttl'])->toBe(PackageVersionChecker::NEGATIVE_TTL);
    });

    it('never reports a development build as outdated', function (): void {
        $checker = new PackageVersionChecker(new VersionSources(new HttpGet, $this->directory.'/composer.json'));

        expect($checker->isUpdateAvailable('dev-main', '2.0.0'))->toBeFalse()
            ->and($checker->isUpdateAvailable('1.3.0', '1.4.0'))->toBeTrue()
            ->and($checker->isUpdateAvailable('1.4.0', '1.4.0'))->toBeFalse();
    });
});

describe('ModuleVersions', function (): void {
    beforeEach(function (): void {
        // A module installed by Composer: its path is a package's install path
        $this->package = 'nwidart/laravel-modules';
        $installed = (string) InstalledVersions::getInstallPath($this->package);
        $local = $this->directory.'/Modules/Local';
        File::ensureDirectoryExists($local);

        $this->app->instance('modules', new readonly class(['Shop' => $installed, 'Local' => $local])
        {
            public function __construct(private array $paths) {}

            public function all(): array
            {
                return array_map(fn (string $name): object => new readonly class($name, $this->paths[$name])
                {
                    public function __construct(private string $name, private string $path) {}

                    public function getName(): string
                    {
                        return $this->name;
                    }

                    public function getPath(): string
                    {
                        return $this->path;
                    }
                }, array_keys($this->paths));
            }
        });
        $this->app->instance(VersionSources::class, new VersionSources(new HttpGet, $this->directory.'/composer.json'));

        fakeHttp(['https://repo.packagist.org/p2/nwidart/laravel-modules.json' => ['packages' => ['nwidart/laravel-modules' => [['version' => '99.0.0']]]]]);
    });

    it('gives a version to modules installed by Composer only', function (): void {
        $versions = $this->app->make(ModuleVersions::class);

        expect(array_keys($versions->all()))->toBe(['Shop'])
            ->and($versions->all()['Shop'])->toMatchArray([
                'package' => $this->package,
                'version' => ltrim((string) InstalledVersions::getPrettyVersion($this->package), 'v'),
                'latest' => null,
                'update' => false,
            ]);
    });

    it('shows the update once refreshed, with the command that applies it', function (): void {
        $this->app->make(ModuleVersions::class)->refresh();
        $versions = $this->app->make(ModuleVersions::class)->all();

        expect($versions['Shop'])->toMatchArray(['latest' => '99.0.0', 'update' => true, 'release_url' => 'https://packagist.org/packages/nwidart/laravel-modules#99.0.0']);

        $result = $this->app->make(ModuleVersionsHealthCheck::class)->test();

        expect($result['status'])->toBe('recommended')
            ->and($result['description'])->toContain('composer update nwidart/laravel-modules');
    });

    it('lists outdated modules with pollora:module:outdated', function (): void {
        $this->app->make(Kernel::class)->registerCommand($this->app->make(ModuleOutdatedCommand::class));

        $this->artisan('pollora:module:outdated')
            ->expectsOutputToContain('composer update nwidart/laravel-modules')
            ->assertSuccessful();
    });
});
