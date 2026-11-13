<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Pollora\Modules\Infrastructure\Services\ModuleScaffolderService;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(function (): void {
    $this->tmp = sys_get_temp_dir().'/pollora-scaffolder-'.uniqid();
    File::ensureDirectoryExists($this->tmp.'/storage');
    $this->app->useStoragePath($this->tmp.'/storage');
    $this->service = new ModuleScaffolderService;
});

afterEach(function (): void {
    File::deleteDirectory($this->tmp);
});

/**
 * @param  array<string, string>  $files  path => content
 */
function scaffoldSource(string $dir, array $files): void
{
    foreach ($files as $path => $content) {
        File::ensureDirectoryExists(dirname($dir.'/'.$path));
        file_put_contents($dir.'/'.$path, $content);
    }
}

describe('ModuleScaffolderService copying', function (): void {
    it('applies placeholders to text files, file names and .stub extensions', function (): void {
        scaffoldSource($this->tmp.'/src', [
            'README.md' => 'Hello {{name}}',
            'app/{{name}}Hooks.stub' => '<?php class {{name}}Hooks {}',
            'assets/deep/app.css' => '/* {{name}} */',
        ]);

        $this->service->copyDirectoryWithReplacements($this->tmp.'/src', $this->tmp.'/out', ['{{name}}' => 'Crm']);

        expect(file_get_contents($this->tmp.'/out/README.md'))->toBe('Hello Crm')
            ->and(file_get_contents($this->tmp.'/out/app/CrmHooks.php'))->toBe('<?php class CrmHooks {}')
            ->and(file_get_contents($this->tmp.'/out/assets/deep/app.css'))->toBe('/* Crm */');
    });

    it('leaves binary files byte for byte intact', function (): void {
        $binary = "\x89PNG\r\n\x1a\n{{name}}\x00\x01";
        scaffoldSource($this->tmp.'/src', ['logo.bin' => $binary]);

        $this->service->copyDirectoryWithReplacements($this->tmp.'/src', $this->tmp.'/out', ['{{name}}' => 'Crm']);

        expect(file_get_contents($this->tmp.'/out/logo.bin'))->toBe($binary);
    });

    it('skips the files a filter refuses', function (): void {
        scaffoldSource($this->tmp.'/src', ['keep.txt' => 'a', 'drop.txt' => 'b']);

        $this->service->copyDirectoryWithReplacements(
            $this->tmp.'/src',
            $this->tmp.'/out',
            [],
            fn (SplFileInfo $file): bool => $file->getFilename() !== 'drop.txt'
        );

        expect(file_exists($this->tmp.'/out/keep.txt'))->toBeTrue()
            ->and(file_exists($this->tmp.'/out/drop.txt'))->toBeFalse();
    });

    it('copies a directory as is, still turning .stub files into .php', function (): void {
        scaffoldSource($this->tmp.'/src', ['a/b/file.txt' => '{{name}}', 'a/Thing.stub' => 'x']);

        $this->service->copyDirectory($this->tmp.'/src', $this->tmp.'/out');

        expect(file_get_contents($this->tmp.'/out/a/b/file.txt'))->toBe('{{name}}')
            ->and(file_exists($this->tmp.'/out/a/Thing.php'))->toBeTrue();
    });

    it('recognises text files by extension, then by content', function (): void {
        scaffoldSource($this->tmp, ['a.TXT' => 'x', 'noext' => 'plain words', 'blob' => "\x00\x01\x02\xff\xfe"]);

        expect($this->service->isTextFile($this->tmp.'/a.TXT', 'TXT'))->toBeTrue()
            ->and($this->service->isTextFile($this->tmp.'/noext', ''))->toBeTrue()
            ->and($this->service->isTextFile($this->tmp.'/blob', ''))->toBeFalse();
    });

    it('removes a directory only when it exists and creates missing ones', function (): void {
        $this->service->ensureDirectoryExists($this->tmp.'/new/deep');
        file_put_contents($this->tmp.'/new/deep/f', 'x');

        expect(is_dir($this->tmp.'/new/deep'))->toBeTrue();

        $this->service->removeDirectory($this->tmp.'/new');
        $this->service->removeDirectory($this->tmp.'/never-existed');

        expect(is_dir($this->tmp.'/new'))->toBeFalse();
    });
});

describe('ModuleScaffolderService target paths', function (): void {
    it('renames the file, turns .stub into .php and nests it under its relative folder', function (): void {
        $info = $this->service->getTargetPathInfo(new SplFileInfo('/x/{{n}}.stub'), '/dest', 'app/Cms', ['{{n}}' => 'Crm']);

        expect($info)->toBe(['dir' => '/dest/app/Cms', 'path' => '/dest/app/Cms/Crm.php']);
    });

    it('does not add a separator for a file at the root', function (): void {
        $info = $this->service->getTargetPathInfo(new SplFileInfo('/x/a.txt'), '/dest', '', []);

        expect($info)->toBe(['dir' => '/dest', 'path' => '/dest/a.txt']);
    });

    it('lets a resolver relocate only the files under app/', function (): void {
        $resolver = fn (string $relative, string $dir, string $path): array => ['dir' => '/moved', 'path' => '/moved/'.basename($path)];

        $inApp = $this->service->getTargetPathInfo(new SplFileInfo('/x/a.php'), '/dest', 'app/Cms', [], $resolver);
        $outside = $this->service->getTargetPathInfo(new SplFileInfo('/x/a.php'), '/dest', 'resources', [], $resolver);
        $declined = $this->service->getTargetPathInfo(new SplFileInfo('/x/a.php'), '/dest', 'app/Cms', [], fn (): ?array => null);

        expect($inApp)->toBe(['dir' => '/moved', 'path' => '/moved/a.php'])
            ->and($outside)->toBe(['dir' => '/dest/resources', 'path' => '/dest/resources/a.php'])
            ->and($declined)->toBe(['dir' => '/dest/app/Cms', 'path' => '/dest/app/Cms/a.php']);
    });
});

describe('ModuleScaffolderService overwriting', function (): void {
    beforeEach(function (): void {
        scaffoldSource($this->tmp, ['src.txt' => 'new {{n}}', 'target.txt' => 'old']);
        $this->item = new SplFileInfo($this->tmp.'/src.txt');
    });

    it('writes a file that does not exist yet without asking', function (): void {
        $asked = false;

        $this->service->handleFileCopy($this->item, $this->tmp.'/fresh.txt', ['{{n}}' => '1'], false, function () use (&$asked): bool {
            $asked = true;

            return false;
        });

        expect($asked)->toBeFalse()
            ->and(file_get_contents($this->tmp.'/fresh.txt'))->toBe('new 1');
    });

    it('keeps the existing file when the user declines to overwrite', function (): void {
        $message = null;

        $this->service->handleFileCopy($this->item, $this->tmp.'/target.txt', [], false, function (string $m) use (&$message): bool {
            $message = $m;

            return false;
        });

        expect(file_get_contents($this->tmp.'/target.txt'))->toBe('old')
            ->and($message)->toContain($this->tmp.'/target.txt');
    });

    it('overwrites when the user agrees', function (): void {
        $this->service->handleFileCopy($this->item, $this->tmp.'/target.txt', ['{{n}}' => '2'], false, fn (): bool => true);

        expect(file_get_contents($this->tmp.'/target.txt'))->toBe('new 2');
    });

    it('overwrites without asking when forced', function (): void {
        $this->service->handleFileCopy($this->item, $this->tmp.'/target.txt', ['{{n}}' => '3'], true, fn (): bool => throw new RuntimeException('must not ask'));

        expect(file_get_contents($this->tmp.'/target.txt'))->toBe('new 3');
    });
});

describe('ModuleScaffolderService downloadAndScaffold', function (): void {
    function scaffoldArchive(string $path): string
    {
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('plugin-main/readme.txt', 'Plugin {{name}}');
        $zip->addFromString('plugin-main/bin/tool.sh', 'x');
        $zip->addFromString('plugin-main/skip.txt', 'skip');
        $zip->close();

        return (string) file_get_contents($path);
    }

    it('downloads, scaffolds with replacements and cleans up the extraction', function (): void {
        Http::fake(['github.com/*' => Http::response(scaffoldArchive($this->tmp.'/a.zip'))]);
        $output = new BufferedOutput;
        $base = $this->tmp.'/plugins';

        $ok = $this->service->downloadAndScaffold(
            'Pollora/plugin-default',
            $base,
            $base.'/crm',
            ['{{name}}' => 'Crm'],
            'v1.0.0',
            $output,
            fn (SplFileInfo $f): bool => $f->getFilename() !== 'skip.txt',
            ['bin'],
        );

        expect($ok)->toBeTrue()
            ->and(file_get_contents($base.'/crm/readme.txt'))->toBe('Plugin Crm')
            ->and(is_dir($base.'/crm/bin'))->toBeFalse()
            ->and(file_exists($base.'/crm/skip.txt'))->toBeFalse()
            ->and(is_dir($base.'/plugin-default'))->toBeFalse()
            ->and($output->fetch())->toContain('Downloading from Pollora/plugin-default (version: v1.0.0)', 'Downloaded and extracted successfully');
    });

    it('keeps the generated module when it is named like the template repository', function (): void {
        Http::fake(['github.com/*' => Http::response(scaffoldArchive($this->tmp.'/a.zip'))]);
        $output = new BufferedOutput;
        $base = $this->tmp.'/plugins';

        $ok = $this->service->downloadAndScaffold('Pollora/plugin-default', $base, $base.'/plugin-default', ['{{name}}' => 'Crm'], 'v1.0.0', $output);

        expect($ok)->toBeTrue()
            ->and(file_get_contents($base.'/plugin-default/readme.txt'))->toBe('Plugin Crm')
            ->and(scandir($base))->toBe(['.', '..', 'plugin-default']);
    });

    it('leaves nothing behind after a success', function (): void {
        Http::fake(['github.com/*' => Http::response(scaffoldArchive($this->tmp.'/a.zip'))]);
        $before = glob(sys_get_temp_dir().'/.pollora-scaffold-*');
        $base = $this->tmp.'/plugins';

        $this->service->downloadAndScaffold('Pollora/plugin-default', $base, $base.'/crm', [], 'v1.0.0', new BufferedOutput);

        expect(scandir($base))->toBe(['.', '..', 'crm'])
            ->and(glob(sys_get_temp_dir().'/.pollora-scaffold-*'))->toBe($before)
            ->and(glob($this->tmp.'/storage/app/tmp/*'))->toBe([]);
    });

    it('leaves nothing behind after a failed download', function (): void {
        Http::fake(['github.com/*' => Http::response('', 404)]);
        $before = glob(sys_get_temp_dir().'/.pollora-scaffold-*');
        $base = $this->tmp.'/plugins';
        File::ensureDirectoryExists($base);

        $ok = $this->service->downloadAndScaffold('Pollora/plugin-default', $base, $base.'/plugin-default', [], 'v9', new BufferedOutput);

        expect($ok)->toBeFalse()
            ->and(scandir($base))->toBe(['.', '..'])
            ->and(glob(sys_get_temp_dir().'/.pollora-scaffold-*'))->toBe($before)
            ->and(glob($this->tmp.'/storage/app/tmp/*'))->toBe([]);
    });

    it('leaves nothing behind when the archive is not a valid zip', function (): void {
        Http::fake(['github.com/*' => Http::response('this is not a zip')]);
        $before = glob(sys_get_temp_dir().'/.pollora-scaffold-*');
        $base = $this->tmp.'/plugins';
        File::ensureDirectoryExists($base);

        $ok = $this->service->downloadAndScaffold('Pollora/plugin-default', $base, $base.'/plugin-default', [], 'v1', new BufferedOutput);

        expect($ok)->toBeFalse()
            ->and(scandir($base))->toBe(['.', '..'])
            ->and(glob(sys_get_temp_dir().'/.pollora-scaffold-*'))->toBe($before)
            ->and(glob($this->tmp.'/storage/app/tmp/*'))->toBe([]);
    });

    it('never deletes a neighbouring directory', function (): void {
        Http::fake(['github.com/*' => Http::response(scaffoldArchive($this->tmp.'/a.zip'))]);
        $base = $this->tmp.'/plugins';
        scaffoldSource($base.'/other-plugin', ['keep.txt' => 'mine']);
        scaffoldSource($base.'/plugin-default-old', ['keep.txt' => 'mine too']);

        $this->service->downloadAndScaffold('Pollora/plugin-default', $base, $base.'/plugin-default', [], 'v1', new BufferedOutput);

        expect(file_get_contents($base.'/other-plugin/keep.txt'))->toBe('mine')
            ->and(file_get_contents($base.'/plugin-default-old/keep.txt'))->toBe('mine too')
            ->and(file_exists($base.'/plugin-default/readme.txt'))->toBeTrue();
    });

    it('reports the failure and asks for the fallback when the download fails', function (): void {
        Http::fake(['github.com/*' => Http::response('', 404)]);
        $output = new BufferedOutput;

        $ok = $this->service->downloadAndScaffold('Pollora/plugin-default', $this->tmp.'/plugins', $this->tmp.'/plugins/crm', [], 'v9', $output);

        expect($ok)->toBeFalse()
            ->and($output->fetch())->toContain('Failed to download', 'Falling back to generating default structure')
            ->and(is_dir($this->tmp.'/plugins/crm'))->toBeFalse();
    });

    it('treats an invalid repository name as a failed download rather than crashing', function (): void {
        $output = new BufferedOutput;

        $ok = $this->service->downloadAndScaffold('not a repo', $this->tmp.'/p', $this->tmp.'/p/x', [], null, $output);

        expect($ok)->toBeFalse()
            ->and($output->fetch())->toContain('Falling back');
    });
});
