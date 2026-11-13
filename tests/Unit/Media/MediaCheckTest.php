<?php

declare(strict_types=1);

use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Media\Domain\Contracts\MediaStorage;
use Pollora\Media\Domain\Models\MediaConfig;
use Pollora\Media\Infrastructure\Laravel\MediaCheck;

/**
 * A disk that lives in an array.
 */
final class ContentMemoryMediaStorage implements MediaStorage
{
    /** @var array<string, string> */
    public array $files = [];

    public bool $failWrites = false;

    public bool $failListing = false;

    public function exists(string $path): bool
    {
        return isset($this->files[$path]);
    }

    public function readStream(string $path)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $this->files[$path]);
        rewind($stream);

        return $stream;
    }

    public function write(string $path, mixed $contents, array $options = []): void
    {
        if ($this->failWrites) {
            throw new RuntimeException('Access denied');
        }

        $this->files[$path] = (string) $contents;
    }

    public function delete(string $path): void
    {
        unset($this->files[$path]);
    }

    public function deleteDirectory(string $path): void {}

    public function copy(string $source, string $destination): void {}

    public function move(string $source, string $destination): void {}

    public function visibility(string $path): string
    {
        return 'public';
    }

    public function setVisibility(string $path, string $visibility): void {}

    public function temporaryUrl(string $path, DateTimeInterface $expiresAt): ?string
    {
        return 'https://signed.test/'.$path;
    }

    public function size(string $path): int
    {
        return strlen($this->files[$path] ?? '');
    }

    public function lastModified(string $path): int
    {
        return 0;
    }

    public function list(string $path, bool $deep = false): array
    {
        if ($this->failListing) {
            throw new RuntimeException('listing refused');
        }

        $found = [];
        foreach (array_keys($this->files) as $file) {
            if (str_starts_with($file, $path.'/')) {
                $found[] = ['path' => $file, 'type' => 'file', 'size' => 1, 'lastModified' => 0];
            }
        }

        return $found;
    }
}

function contentMediaCheck(?string $disk, ContentMemoryMediaStorage $storage, string $localUploads = '/nonexistent/uploads', ?Closure $thumbnailProblems = null): MediaCheck
{
    return new MediaCheck(
        $disk,
        fn (): MediaStorage => $storage,
        fn (): MediaConfig => new MediaConfig('https://cdn.test/uploads/'),
        $localUploads,
        null,
        $thumbnailProblems,
    );
}

describe('MediaCheck', function (): void {
    beforeEach(function (): void {
        $this->storage = new ContentMemoryMediaStorage;
        $this->tmp = sys_get_temp_dir().'/pollora-content-media-'.bin2hex(random_bytes(5));
    });

    afterEach(function (): void {
        if (is_dir($this->tmp)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->tmp);
        }
    });

    it('presents itself to the doctor and runs in the console and over HTTP', function (): void {
        $check = contentMediaCheck('uploads', $this->storage);

        expect($check->id())->toBe('media')
            ->and($check->label())->toBe('Media storage')
            ->and($check->runsIn())->toBe([RunContext::Console, RunContext::Http]);
    });

    it('is skipped when no media disk is configured', function (): void {
        $result = contentMediaCheck(null, $this->storage)->run(RunContext::Console);

        expect($result->status->name)->toBe('Skipped')
            ->and($result->summary)->toContain('media.disk is not set');
    });

    it('reports an error, with the cause, when the disk does not take a file', function (): void {
        $this->storage->failWrites = true;

        $result = contentMediaCheck('uploads', $this->storage)->run(RunContext::Console);

        expect($result->status->name)->toBe('Error')
            ->and($result->summary)->toContain('"uploads" does not take a file')
            ->and($result->details)->toBe(['Access denied'])
            ->and($result->fix)->toContain('pollora:media:status');
    });

    it('is fine when the disk works and nothing else is wrong', function (): void {
        $result = contentMediaCheck('uploads', $this->storage)->run(RunContext::Console);

        expect($result->status->name)->toBe('Ok')
            ->and($result->summary)->toContain('"uploads" takes a file')->toContain('https://cdn.test/uploads');
    });

    it('warns of folders of private files that sit on the public disk', function (): void {
        $this->storage->files['woocommerce_uploads/invoice.pdf'] = 'x';
        $this->storage->files['wc-logs/log.txt'] = 'x';
        $this->storage->files['2026/photo.jpg'] = 'x';

        $result = contentMediaCheck('uploads', $this->storage)->run(RunContext::Console);

        expect($result->status->name)->toBe('Warning')
            ->and($result->summary)->toContain('2 folder(s)')->toContain('woocommerce_uploads')->toContain('wc-logs')
            ->and($result->details)->toBe(['woocommerce_uploads/ is served publicly', 'wc-logs/ is served publicly'])
            ->and($result->fix)->toContain('--exclude=woocommerce_uploads/*');
    });

    it('does not let a disk that cannot list a folder fail the check', function (): void {
        $this->storage->failListing = true;

        expect(contentMediaCheck('uploads', $this->storage)->run(RunContext::Console)->status->name)->toBe('Ok');
    });

    it('warns of the problems the thumbnail driver reports', function (): void {
        $result = contentMediaCheck('uploads', $this->storage, thumbnailProblems: fn (): array => ['Bunny is not configured'])->run(RunContext::Console);

        expect($result->status->name)->toBe('Warning')
            ->and($result->summary)->toContain('1 problem(s) with the declared image sizes')
            ->and($result->details)->toBe(['Bunny is not configured']);
    });

    it('warns of an uploads .htaccess that restarts Apache rewriting', function (): void {
        mkdir($this->tmp, 0777, true);
        file_put_contents($this->tmp.'/.htaccess', "# security\nRewriteEngine On\nRewriteRule ^ - [F]\n");

        $result = contentMediaCheck('uploads', $this->storage, $this->tmp)->run(RunContext::Console);

        expect($result->status->name)->toBe('Warning')
            ->and($result->summary)->toContain('.htaccess')
            ->and($result->details[0])->toContain($this->tmp.'/.htaccess')->toContain('RewriteEngine On');
    });

    it('ignores an uploads .htaccess that does not restart rewriting', function (): void {
        mkdir($this->tmp, 0777, true);
        file_put_contents($this->tmp.'/.htaccess', "Options -Indexes\n");
        $this->storage->files['.htaccess'] = 'x';

        expect(contentMediaCheck('uploads', $this->storage, $this->tmp)->run(RunContext::Console)->status->name)->toBe('Ok');
    });

    it('does not count the private folders of the local uploads as files the migration forgot', function (): void {
        mkdir($this->tmp.'/wpforms', 0777, true);
        file_put_contents($this->tmp.'/wpforms/entry.json', 'x');

        expect(contentMediaCheck('uploads', $this->storage, $this->tmp)->run(RunContext::Console)->status->name)->toBe('Ok');
    });

    it('lists the local files that are not on the disk, five at most in the details', function (): void {
        mkdir($this->tmp, 0777, true);
        foreach (range(1, 7) as $n) {
            file_put_contents($this->tmp.'/file'.$n.'.jpg', 'x');
        }

        $result = contentMediaCheck('uploads', $this->storage, $this->tmp)->run(RunContext::Console);

        expect($result->status->name)->toBe('Warning')
            ->and($result->summary)->toContain('7 file(s)')
            ->and($result->details)->toHaveCount(5);
    });
});
