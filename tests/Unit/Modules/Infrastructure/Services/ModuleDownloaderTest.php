<?php

declare(strict_types=1);

use Pollora\Modules\Infrastructure\Services\ModuleDownloader;

describe('ModuleDownloader validation', function (): void {
    it('accepts valid repository names', function (string $repo): void {
        $downloader = new ModuleDownloader($repo);

        expect($downloader)->toBeInstanceOf(ModuleDownloader::class);
    })->with([
        'standard' => ['Pollora/theme-default'],
        'simple' => ['owner/repo'],
        'with dots' => ['org.name/repo.name'],
        'with underscores' => ['my_org/my_repo'],
        'with hyphens' => ['my-org/my-repo'],
        'single char' => ['a/b'],
        'numbers' => ['org123/repo456'],
        'mixed' => ['My-Org.123/Theme_v2.0'],
    ]);

    it('rejects invalid repository names', function (string $repo): void {
        expect(fn () => new ModuleDownloader($repo))
            ->toThrow(InvalidArgumentException::class);
    })->with([
        'no slash' => ['owner-repo'],
        'empty repo' => ['owner/'],
        'empty owner' => ['/repo'],
        'path traversal' => ['../etc/passwd'],
        'triple segment' => ['owner/repo/extra'],
        'spaces' => ['owner/ repo'],
        'leading hyphen owner' => ['-owner/repo'],
        'leading hyphen repo' => ['owner/-repo'],
        'special chars' => ['owner/repo;rm -rf'],
        'empty string' => [''],
        'just slash' => ['/'],
    ]);
});
