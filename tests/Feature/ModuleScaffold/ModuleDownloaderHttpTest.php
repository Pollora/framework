<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Pollora\Modules\Domain\Contracts\ModuleInterface;
use Pollora\Modules\Domain\Exceptions\ModuleException;
use Pollora\Modules\Infrastructure\Services\ModuleDownloader;

/**
 * A downloader whose scratch files stay in a directory the test owns.
 */
function downloaderIn(string $tmp, string $repository = 'Pollora/theme-default'): ModuleDownloader
{
    return new class($repository, $tmp) extends ModuleDownloader
    {
        public function __construct(string $repository, private readonly string $tmp)
        {
            parent::__construct($repository);
        }

        protected function getTempPath(string $filename): string
        {
            return $this->tmp.'/scratch/'.$filename;
        }
    };
}

function githubZip(string $path, string $rootFolder = 'theme-default-1.0.0'): string
{
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString($rootFolder.'/style.css', '/* theme */');
    $zip->close();

    return (string) file_get_contents($path);
}

beforeEach(function (): void {
    $this->tmp = sys_get_temp_dir().'/pollora-downloader-'.uniqid();
    File::ensureDirectoryExists($this->tmp);
});

afterEach(function (): void {
    File::deleteDirectory($this->tmp);
});

describe('ModuleDownloader over HTTP', function (): void {
    it('lists the tag names of the repository', function (): void {
        Http::fake(['api.github.com/repos/Pollora/theme-default/tags' => Http::response([['name' => 'v1.0.0'], ['name' => 'v1.1.0']])]);

        expect(downloaderIn($this->tmp)->getAvailableVersions())->toBe(['v1.0.0', 'v1.1.0']);
    });

    it('lists no version when GitHub refuses the request', function (): void {
        Http::fake(['api.github.com/*' => Http::response('', 500)]);

        expect(downloaderIn($this->tmp)->getAvailableVersions())->toBe([]);
    });

    it('returns the repository information', function (): void {
        Http::fake(['api.github.com/repos/Pollora/theme-default' => Http::response(['default_branch' => 'trunk'])]);

        expect(downloaderIn($this->tmp)->getRepositoryInfo())->toBe(['default_branch' => 'trunk']);
    });

    it('throws a module exception when the repository is unreachable', function (): void {
        Http::fake(['api.github.com/*' => Http::response('', 404)]);

        expect(fn (): array => downloaderIn($this->tmp)->getRepositoryInfo())->toThrow(ModuleException::class);
    });

    it('downloads the requested tag and returns the folder nested in the archive', function (): void {
        $archive = githubZip($this->tmp.'/source.zip');
        Http::fake(['github.com/Pollora/theme-default/archive/refs/tags/v1.2.0.zip' => Http::response($archive)]);
        $destination = $this->tmp.'/themes';

        $path = downloaderIn($this->tmp)->setVersion('v1.2.0')->downloadAndExtract($destination);

        expect($path)->toBe($destination.'/theme-default/theme-default-1.0.0')
            ->and(file_get_contents($path.'/style.css'))->toBe('/* theme */')
            ->and(glob($this->tmp.'/scratch/*'))->toBe([]);
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/archive/refs/tags/v1.2.0.zip'));
    });

    it('picks the highest stable tag when no version is requested', function (): void {
        $archive = githubZip($this->tmp.'/source.zip');
        Http::fake([
            'api.github.com/repos/Pollora/theme-default/tags*' => Http::response([['name' => 'v1.2.1'], ['name' => 'v1.4.0'], ['name' => 'v2.0.0-beta.1']]),
            'github.com/Pollora/theme-default/archive/refs/tags/v1.4.0.zip' => Http::response($archive),
        ]);

        downloaderIn($this->tmp)->downloadAndExtract($this->tmp.'/themes');

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/archive/refs/tags/v1.4.0.zip'));
    });

    it('falls back to the default branch when the repository has no tag', function (): void {
        $archive = githubZip($this->tmp.'/source.zip');
        Http::fake([
            'api.github.com/repos/Pollora/theme-default/tags*' => Http::response([]),
            'api.github.com/repos/Pollora/theme-default' => Http::response(['default_branch' => 'trunk']),
            'github.com/Pollora/theme-default/archive/refs/heads/trunk.zip' => Http::response($archive),
        ]);

        downloaderIn($this->tmp)->downloadAndExtract($this->tmp.'/themes');

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/archive/refs/heads/trunk.zip'));
    });

    it('uses main when GitHub cannot say what the default branch is', function (): void {
        $archive = githubZip($this->tmp.'/source.zip');
        Http::fake([
            'api.github.com/*' => Http::response('', 500),
            'github.com/Pollora/theme-default/archive/refs/heads/main.zip' => Http::response($archive),
        ]);

        downloaderIn($this->tmp)->downloadAndExtract($this->tmp.'/themes');

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/archive/refs/heads/main.zip'));
    });

    it('throws and leaves nothing behind when the archive cannot be downloaded', function (): void {
        Http::fake(['github.com/*' => Http::response('', 404)]);

        expect(fn (): string => downloaderIn($this->tmp)->setVersion('v9')->downloadAndExtract($this->tmp.'/themes'))
            ->toThrow(ModuleException::class);
        expect(glob($this->tmp.'/scratch/*'))->toBe([]);
    });

    it('throws when the downloaded file is not a readable archive', function (): void {
        Http::fake(['github.com/*' => Http::response('this is not a zip')]);

        expect(fn (): string => downloaderIn($this->tmp)->setVersion('v1')->downloadAndExtract($this->tmp.'/themes'))
            ->toThrow(ModuleException::class);
        expect(glob($this->tmp.'/scratch/*'))->toBe([]);
    });

    it('throws when the archive holds no folder', function (): void {
        $zip = new ZipArchive;
        $zip->open($this->tmp.'/flat.zip', ZipArchive::CREATE);
        $zip->addFromString('readme.txt', 'x');
        $zip->close();
        Http::fake(['github.com/*' => Http::response((string) file_get_contents($this->tmp.'/flat.zip'))]);

        expect(fn (): string => downloaderIn($this->tmp)->setVersion('v1')->downloadAndExtract($this->tmp.'/themes'))
            ->toThrow(ModuleException::class);
    });

    it('extracts a module into a folder named after it', function (): void {
        $archive = githubZip($this->tmp.'/source.zip');
        Http::fake(['github.com/*' => Http::response($archive)]);
        $module = Mockery::mock(ModuleInterface::class);
        $module->shouldReceive('getName')->andReturn('Crm');

        $path = downloaderIn($this->tmp)->setVersion('v1')->downloadForModule($module, $this->tmp.'/modules/');

        expect($path)->toBe($this->tmp.'/modules/Crm/theme-default/theme-default-1.0.0')
            ->and(file_exists($path.'/style.css'))->toBeTrue();
    });
});

/**
 * A downloader with a tiny ceiling, and a way to ask the transfer guard directly.
 */
function capDownloaderIn(string $tmp, int $max): ModuleDownloader
{
    return new class('Pollora/theme-default', $tmp, $max) extends ModuleDownloader
    {
        public function __construct(string $repository, private readonly string $tmp, private readonly int $max)
        {
            parent::__construct($repository);
        }

        protected function getTempPath(string $filename): string
        {
            return $this->tmp.'/scratch/'.$filename;
        }

        protected function maxArchiveBytes(): int
        {
            return $this->max;
        }

        public function transferExceeds(int $announced, int $received): bool
        {
            return $this->exceedsCeiling($announced, $received);
        }
    };
}

describe('ModuleDownloader archive size ceiling', function (): void {
    it('refuses an archive whose Content-Length is over the ceiling and leaves nothing behind', function (): void {
        Http::fake(['github.com/*' => Http::response('x', 200, ['Content-Length' => '5000'])]);

        expect(fn (): string => capDownloaderIn($this->tmp, 1000)->setVersion('v1')->downloadAndExtract($this->tmp.'/themes'))
            ->toThrow(ModuleException::class, 'ceiling');
        expect(glob($this->tmp.'/scratch/*'))->toBe([]);
    });

    it('refuses an archive larger than the ceiling whatever the headers say', function (): void {
        Http::fake(['github.com/*' => Http::response(str_repeat('x', 2000))]);

        expect(fn (): string => capDownloaderIn($this->tmp, 1000)->setVersion('v1')->downloadAndExtract($this->tmp.'/themes'))
            ->toThrow(ModuleException::class, 'ceiling');
        expect(glob($this->tmp.'/scratch/*'))->toBe([]);
    });

    it('accepts an archive exactly at the ceiling', function (): void {
        $archive = githubZip($this->tmp.'/source.zip');
        Http::fake(['github.com/*' => Http::response($archive, 200, ['Content-Length' => (string) strlen($archive)])]);

        $path = capDownloaderIn($this->tmp, strlen($archive))->setVersion('v1')->downloadAndExtract($this->tmp.'/themes');

        expect(file_exists($path.'/style.css'))->toBeTrue();
    });

    it('judges a running transfer on its announced and its received size', function (): void {
        $downloader = capDownloaderIn($this->tmp, 1000);

        expect($downloader->transferExceeds(0, 0))->toBeFalse()
            ->and($downloader->transferExceeds(1000, 1000))->toBeFalse()
            ->and($downloader->transferExceeds(1001, 0))->toBeTrue()
            ->and($downloader->transferExceeds(0, 1001))->toBeTrue();
    });

    it('has a ceiling of 100 MiB by default', function (): void {
        $probe = new class('Pollora/theme-default') extends ModuleDownloader
        {
            public function ceiling(): int
            {
                return $this->maxArchiveBytes();
            }
        };

        expect($probe->ceiling())->toBe(100 * 1024 * 1024);
    });
});

describe('ModuleDownloader tags returned by GitHub', function (): void {
    it('ignores a tag name that setVersion() would refuse', function (): void {
        $archive = githubZip($this->tmp.'/source.zip');
        Http::fake([
            'api.github.com/repos/Pollora/theme-default/tags*' => Http::response([['name' => '9.9.9/../../evil'], ['name' => "9.0.0\nx"], ['name' => 'v1.0.0']]),
            'github.com/Pollora/theme-default/archive/refs/tags/v1.0.0.zip' => Http::response($archive),
        ]);

        downloaderIn($this->tmp)->downloadAndExtract($this->tmp.'/themes');

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/archive/refs/tags/v1.0.0.zip'));
    });

    it('falls back to the default branch when no returned tag is acceptable', function (): void {
        $archive = githubZip($this->tmp.'/source.zip');
        Http::fake([
            'api.github.com/repos/Pollora/theme-default/tags*' => Http::response([['name' => '1.0/../x'], ['name' => 42]]),
            'api.github.com/repos/Pollora/theme-default' => Http::response(['default_branch' => 'main']),
            'github.com/Pollora/theme-default/archive/refs/heads/main.zip' => Http::response($archive),
        ]);

        downloaderIn($this->tmp)->downloadAndExtract($this->tmp.'/themes');

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/archive/refs/heads/main.zip'));
    });

    it('uses main when the default branch GitHub returns is not a plain branch name', function (): void {
        $archive = githubZip($this->tmp.'/source.zip');
        Http::fake([
            'api.github.com/repos/Pollora/theme-default/tags*' => Http::response([]),
            'api.github.com/repos/Pollora/theme-default' => Http::response(['default_branch' => '../../other/repo/archive']),
            'github.com/Pollora/theme-default/archive/refs/heads/main.zip' => Http::response($archive),
        ]);

        downloaderIn($this->tmp)->downloadAndExtract($this->tmp.'/themes');

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/archive/refs/heads/main.zip'));
    });

    it('keeps a branch name with a slash', function (): void {
        $archive = githubZip($this->tmp.'/source.zip');
        Http::fake([
            'api.github.com/repos/Pollora/theme-default/tags*' => Http::response([]),
            'api.github.com/repos/Pollora/theme-default' => Http::response(['default_branch' => 'release/2.x']),
            'github.com/Pollora/theme-default/archive/refs/heads/release/2.x.zip' => Http::response($archive),
        ]);

        downloaderIn($this->tmp)->downloadAndExtract($this->tmp.'/themes');

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/archive/refs/heads/release/2.x.zip'));
    });
});
