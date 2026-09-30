<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Illuminate\Filesystem\Filesystem;
use Pollora\Application\Domain\Contracts\DebugDetectorInterface;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Services\DiscoveryCacheManager;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Infrastructure\Checks\DiscoveryCacheCheck;
use Pollora\Doctor\Infrastructure\Checks\EnvironmentFileCheck;
use Pollora\Doctor\Infrastructure\Checks\PatchesLockCheck;
use Pollora\Doctor\Infrastructure\Checks\WordPressCorePatchCheck;
use Spatie\StructureDiscoverer\Cache\DiscoverCacheDriver;

if (! function_exists('putFile')) {
    function putFile(string $path, string $contents): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
    }
}

beforeEach(function (): void {
    $this->project = sys_get_temp_dir().'/pollora-doctor-'.uniqid();
    mkdir($this->project, 0777, true);
    $this->previousBasePath = app()->basePath();
    app()->setBasePath($this->project);
});

afterEach(function (): void {
    app()->setBasePath($this->previousBasePath);
    (new Filesystem)->deleteDirectory($this->project);
});

function corePatchCheck(string $project, bool $overrideActive): WordPressCorePatchCheck
{
    return new WordPressCorePatchCheck($project.'/wp-includes', fn (): array => [
        'override_active' => $overrideActive,
        'helper_file' => '/vendor/laravel/framework/src/Illuminate/Foundation/helpers.php',
    ]);
}

describe('WordPress core patch', function (): void {
    beforeEach(function (): void {
        mkdir($this->project.'/wp-includes');
    });

    it('fails when the core still declares __(), as composer-patches leaves it when it skips the patch', function (): void {
        file_put_contents($this->project.'/wp-includes/l10n.php', "<?php\nfunction __( \$text, \$domain = 'default' ) {}\n");

        $result = corePatchCheck($this->project, true)->run(RunContext::Console);

        expect($result->status->value)->toBe('error')
            ->and($result->fix)->toBe('composer patches-relock && composer patches-repatch');
    });

    it("warns when the core is patched but __() is not Pollora's", function (): void {
        file_put_contents($this->project.'/wp-includes/l10n.php', "<?php\nfunction __wp( \$text, \$domain = 'default' ) {}\n");

        $result = corePatchCheck($this->project, false)->run(RunContext::Console);

        expect($result->status->value)->toBe('warning')
            ->and($result->details[0])->toContain('laravel/framework');
    });

    it("passes a patched core whose __() is Pollora's", function (): void {
        file_put_contents($this->project.'/wp-includes/l10n.php', "<?php\nfunction __wp( \$text, \$domain = 'default' ) {}\n");

        expect(corePatchCheck($this->project, true)->run(RunContext::Console)->status->value)->toBe('ok');
    });
});

describe('Composer patches lock', function (): void {
    /** The patches the installed framework declares — this repository's own composer.json. */
    function declaredFrameworkPatches(): array
    {
        return json_decode((string) file_get_contents(InstalledVersions::getInstallPath('pollora/framework').'/composer.json'), true)['extra']['patches'];
    }

    function writeLock(string $project, array $patches): void
    {
        $lock = [];

        foreach ($patches as $package => $entries) {
            foreach ($entries as $description => $url) {
                $lock[$package][] = ['package' => $package, 'description' => $description, 'url' => $url, 'sha256' => 'x'];
            }
        }

        file_put_contents($project.'/patches.lock.json', json_encode(['_hash' => 'x', 'patches' => $lock]));
    }

    it('fails without patches.lock.json', function (): void {
        expect((new PatchesLockCheck)->run(RunContext::Console)->status->value)->toBe('error');
    });

    it('fails when the lock pins an older patch than the framework declares', function (): void {
        $patches = declaredFrameworkPatches();
        $package = array_key_first($patches);
        $patches[$package][array_key_first($patches[$package])] = 'https://raw.githubusercontent.com/Pollora/framework/an-old-commit/patches/wordpress-core.patch';
        writeLock($this->project, $patches);

        $result = (new PatchesLockCheck)->run(RunContext::Console);

        expect($result->status->value)->toBe('error')
            ->and($result->details[0])->toStartWith($package.': ');
    });

    it('passes a lock that lists every declared patch', function (): void {
        writeLock($this->project, declaredFrameworkPatches());

        expect((new PatchesLockCheck)->run(RunContext::Console)->status->value)->toBe('ok');
    });
});

describe('Environment file', function (): void {
    it('fails on a Bedrock name Pollora does not read', function (): void {
        file_put_contents($this->project.'/.env', "APP_URL=https://site.test\nDB_NAME=site\nDB_USERNAME=db\n");

        $result = (new EnvironmentFileCheck)->run(RunContext::Console);

        expect($result->status->value)->toBe('error')
            ->and($result->details)->toContain('DB_NAME is not read: Pollora reads DB_DATABASE');
    });

    it('fails when MySQL settings sit on a sqlite connection', function (): void {
        config(['database.default' => 'sqlite']);
        file_put_contents($this->project.'/.env', "DB_HOST=db\nDB_DATABASE=db\n");

        expect((new EnvironmentFileCheck)->run(RunContext::Console)->details)
            ->toContain('DB_HOST is set, but the connection is sqlite: DB_CONNECTION is missing or not mysql');
    });

    it('warns when WP_HOME disagrees with the APP_URL in use', function (): void {
        config(['database.default' => 'mysql']);
        file_put_contents($this->project.'/.env', "APP_URL=https://site.test\nWP_HOME=https://old.test\n");

        expect((new EnvironmentFileCheck)->run(RunContext::Console)->status->value)->toBe('warning');
    });

    it("passes Laravel's names", function (): void {
        config(['database.default' => 'mysql']);
        file_put_contents($this->project.'/.env', "APP_URL=https://site.test\nDB_CONNECTION=mysql\nDB_HOST=db\nDB_DATABASE=db\nDB_USERNAME=db\n");

        expect((new EnvironmentFileCheck)->run(RunContext::Console)->status->value)->toBe('ok');
    });
});

describe('Discovery cache', function (): void {
    function discoveryCheck(bool $cacheEnabled, array $missing): DiscoveryCacheCheck
    {
        $location = Mockery::mock(DiscoveryLocationInterface::class);
        $manager = Mockery::mock(DiscoveryManager::class);
        $manager->shouldReceive('getLocations')->andReturn(collect([$location]));
        $cache = Mockery::mock(DiscoveryCacheManager::class);
        $cache->shouldReceive('isCacheEnabled')->andReturn($cacheEnabled);
        $cache->shouldReceive('classesMissingFromCache')->with($location)->andReturn($missing);

        return new DiscoveryCacheCheck($manager, $cache);
    }

    it('fails when classes on disk are missing from the cache, and names them', function (): void {
        $result = discoveryCheck(true, ['App\\Hooks\\NewAction'])->run(RunContext::Console);

        expect($result->status->value)->toBe('error')
            ->and($result->details)->toBe(['App\\Hooks\\NewAction'])
            ->and($result->fix)->toBe('php artisan discovery:clear');
    });

    it('passes when the cache is off', function (): void {
        expect(discoveryCheck(false, ['App\\Anything'])->run(RunContext::Console)->status->value)->toBe('ok');
    });
});

describe('DiscoveryCacheManager::classesMissingFromCache()', function (): void {
    /** A cache driver holding what a test puts in it. */
    final class DoctorArrayDiscoverCacheDriver implements DiscoverCacheDriver
    {
        /** @var array<string, array<mixed>> */
        public static array $entries = [];

        public function has(string $id): bool
        {
            return array_key_exists($id, self::$entries);
        }

        public function get(string $id): array
        {
            return self::$entries[$id];
        }

        public function put(string $id, array $discovered): void
        {
            self::$entries[$id] = $discovered;
        }

        public function forget(string $id): void
        {
            unset(self::$entries[$id]);
        }
    }

    function cacheManager(bool $debug): DiscoveryCacheManager
    {
        config(['structure-discoverer.cache.driver' => DoctorArrayDiscoverCacheDriver::class]);
        $detector = Mockery::mock(DebugDetectorInterface::class);
        $detector->shouldReceive('isDebugMode')->andReturn($debug);

        return new DiscoveryCacheManager(app(), $detector);
    }

    beforeEach(function (): void {
        DoctorArrayDiscoverCacheDriver::$entries = [];
        putFile($this->project.'/app/Hooks/NewAction.php', "<?php\nnamespace DoctorFixture\\Hooks;\nfinal class NewAction {}\n");
        $this->location = new DiscoveryLocation('DoctorFixture\\', $this->project.'/app');
    });

    it('names a class on disk that the cached entry lacks', function (): void {
        DoctorArrayDiscoverCacheDriver::$entries['discovery_'.md5($this->location->getPath())] = [];

        expect(cacheManager(debug: false)->classesMissingFromCache($this->location))->toBe(['DoctorFixture\\Hooks\\NewAction']);
    });

    it('has nothing to compare when the location was never cached, or in debug mode', function (): void {
        expect(cacheManager(debug: false)->classesMissingFromCache($this->location))->toBeNull()
            ->and(cacheManager(debug: true)->classesMissingFromCache($this->location))->toBeNull();
    });
});
