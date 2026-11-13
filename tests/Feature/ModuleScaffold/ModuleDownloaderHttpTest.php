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
