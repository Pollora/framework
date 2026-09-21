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
        expect(fn (): ModuleDownloader => new ModuleDownloader($repo))
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

/**
 * Which tag `make:theme` and `make:plugin` hand to people.
 *
 * "Latest" used to mean the first tag the GitHub API returned, which is
 * whatever was pushed most recently. A patch on an older line — 1.2.1
 * published after 1.4.0 — would then be served to everyone scaffolding, a
 * silent downgrade with no way to notice.
 */
function latestTagAmong(array $names): ?string
{
    return (new ModuleDownloader('Pollora/theme-default'))->selectLatestVersion($names);
}

describe('ModuleDownloader::selectLatestVersion()', function (): void {
    it('takes the highest version, not the one the API happens to list first', function (): void {
        // The case this exists for: a fix tagged on an older line afterwards.
        expect(latestTagAmong(['v1.2.1', 'v1.4.0', 'v1.2.0']))->toBe('v1.4.0');
    });

    it('compares numerically, so 1.10 beats 1.9', function (): void {
        expect(latestTagAmong(['v1.9.0', 'v1.10.0']))->toBe('v1.10.0');
    });

    it('does not push a pre-release onto someone who asked for nothing', function (): void {
        expect(latestTagAmong(['v2.0.0-beta.1', 'v1.4.0']))->toBe('v1.4.0');
    });

    it('falls back to pre-releases when a package has never had a stable one', function (): void {
        expect(latestTagAmong(['v1.0.0-beta.2', 'v1.0.0-beta.10']))->toBe('v1.0.0-beta.10');
    });

    it('ignores tags that are not versions', function (): void {
        expect(latestTagAmong(['nightly', 'v1.4.0', 'latest']))->toBe('v1.4.0');
    });

    it('keeps working for a repository tagged some other way', function (): void {
        // Nothing parses, so the previous behaviour stands rather than nothing.
        expect(latestTagAmong(['nightly', 'stable']))->toBe('nightly');
    });

    it('handles the tags these two repositories actually carry', function (): void {
        expect(latestTagAmong(['v1.4.0', 'v1.2.0', '1.1.2', '1.1.1', '1.1.0', '1.0.3']))->toBe('v1.4.0');
        expect(latestTagAmong(['0.8', '0.7', '0.6', '0.5']))->toBe('0.8');
    });

    it('returns nothing when the repository has no tags', function (): void {
        expect(latestTagAmong([]))->toBeNull();
    });
});
