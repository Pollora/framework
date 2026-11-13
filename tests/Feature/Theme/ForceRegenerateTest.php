<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\TextPrompt;
use Mockery as m;
use Pollora\Console\Concerns\ReplacesExistingDirectory;
use Pollora\Modules\Infrastructure\Services\ModuleScaffolderService;
use Pollora\Plugin\UI\Console\MakePluginCommand;
use Pollora\Theme\UI\Console\MakeThemeCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `--force` on pollora:make:theme and pollora:make:plugin: delete the existing folder, then generate again.
 *
 * Nothing is deleted without --force, an interactive terminal and the exact name typed back; the
 * active theme or plugin, links, and anything that is not a folder directly inside the root stay.
 */
function forceTempDir(): string
{
    $dir = sys_get_temp_dir().'/pollora-force-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);

    return realpath($dir);
}

/**
 * @param  array<int, string>  $active  What WordPress would report as active (theme names, or plugin files).
 */
function forceCommand(string $kind, string $root, array $active = []): Command
{
    $class = $kind === 'theme' ? MakeThemeCommand::class : MakePluginCommand::class;

    if ($kind === 'theme') {
        $command = new class(new Repository(['theme' => ['path' => $root]]), new Filesystem) extends MakeThemeCommand
        {
            public array $active = [];

            protected function activeThemeNames(): array
            {
                return $this->active;
            }

            protected function runNpmIfNeeded(): void {}
        };
        $command->setDefinition((new MakeThemeCommand(new Repository, new Filesystem))->getDefinition());
    } else {
        $command = new class(new Repository, new Filesystem, resolve(ModuleScaffolderService::class), $root) extends MakePluginCommand
        {
            public array $active = [];

            public function __construct(Repository $config, Filesystem $files, ModuleScaffolderService $scaffolder, private readonly string $root)
            {
                parent::__construct($config, $files, $scaffolder);
            }

            protected function getPluginsPath(): string
            {
                return $this->root;
            }

            protected function activePluginFiles(): array
            {
                return $this->active;
            }
        };
        $command->setDefinition((new $class(new Repository, new Filesystem, resolve(ModuleScaffolderService::class)))->getDefinition());
    }

    $command->active = $active;
    $command->setLaravel(app());

    return $command;
}

/**
 * @return array{0: int, 1: string}
 */
function forceRun(Command $command, string $kind, string $name, array $options = [], bool $interactive = true): array
{
    $options = [
        "--{$kind}-author" => 'Ann', "--{$kind}-author-uri" => 'https://a.test', "--{$kind}-uri" => 'https://a.test',
        "--{$kind}-description" => 'd', "--{$kind}-version" => '1.0', '--repository' => 'acme/starter',
        ...($kind === 'theme' ? ['--no-activate' => true] : ['--activate-plugin' => 'no', '--asset' => 'false']),
        ...$options,
    ];
    $input = new ArrayInput(['name' => $name, ...$options]);
    $input->setInteractive($interactive);

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, (forceTyping() ?? '')."\n");
    rewind($stream);
    $input->setStream($stream);
    $output = new BufferedOutput;

    return [$command->run($input, $output), $output->fetch()];
}

/**
 * A scaffolder that records what was in the way when the download started, and writes a new file.
 */
function forceScaffolder(?array &$seen = null): ModuleScaffolderService
{
    $scaffolder = m::mock(ModuleScaffolderService::class);
    $scaffolder->shouldReceive('downloadAndScaffold')->andReturnUsing(function (string $repository, string $basePath, string $targetPath) use (&$seen): bool {
        $seen = ['target_existed' => file_exists($targetPath), 'mine_existed' => file_exists($targetPath.'/mine.txt')];
        mkdir($targetPath, 0777, true);
        file_put_contents($targetPath.'/generated.txt', 'new');

        return true;
    });
    app()->instance(ModuleScaffolderService::class, $scaffolder);

    return $scaffolder;
}

/**
 * What the person types at the next prompt. Command::run() installs its own fallback for the
 * prompts, which asks Symfony's question helper: the answer goes through the input stream.
 */
function forceTyping(?string $answer = null): ?string
{
    static $typed = null;

    if ($answer !== null) {
        $typed = $answer;
        TextPrompt::fallbackUsing(fn (): string => $answer);
    }

    return $typed;
}

beforeEach(function (): void {
    $this->root = forceTempDir();
    Prompt::fallbackWhen(true);
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->root);
    Prompt::fallbackWhen(false);
    TextPrompt::fallbackUsing(fn (TextPrompt $prompt): string => $prompt->default);
    forceTyping('');
});

describe('without --force', function (): void {
    it('refuses an existing folder with the usual message and deletes nothing', function (string $kind, string $message): void {
        mkdir($this->root.'/taken');
        file_put_contents($this->root.'/taken/mine.txt', 'mine');
        $scaffolder = m::mock(ModuleScaffolderService::class);
        $scaffolder->shouldNotReceive('downloadAndScaffold');

        app()->instance(ModuleScaffolderService::class, $scaffolder);

        [$code, $output] = forceRun(forceCommand($kind, $this->root), $kind, 'taken');

        expect($code)->toBe(1)
            ->and($output)->toContain($message)
            ->and($output)->not->toContain('DELETED')
            ->and(file_get_contents($this->root.'/taken/mine.txt'))->toBe('mine');
    })->with([
        'theme' => ['theme', 'Theme "taken" already exists.'],
        'plugin' => ['plugin', 'Plugin "taken" already exists.'],
    ]);

    it('still generates a new folder as before', function (string $kind): void {
        $seen = null;
        forceScaffolder($seen);

        [$code] = forceRun(forceCommand($kind, $this->root), $kind, 'fresh');

        expect($code)->toBe(0)->and($seen['target_existed'])->toBeFalse()
            ->and(file_exists($this->root.'/fresh/generated.txt'))->toBeTrue();
    })->with(['theme', 'plugin']);
});

describe('with --force', function (): void {
    it('deletes the existing folder when its exact name is typed, then generates it as a new one', function (string $kind): void {
        mkdir($this->root.'/taken/sub', 0777, true);
        file_put_contents($this->root.'/taken/mine.txt', 'mine');
        file_put_contents($this->root.'/taken/sub/deep.txt', 'deep');
        $seen = null;
        forceScaffolder($seen);
        forceTyping('taken');

        [$code, $output] = forceRun(forceCommand($kind, $this->root), $kind, 'taken', ['--force' => true]);

        expect($code)->toBe(0)
            ->and($seen)->toBe(['target_existed' => false, 'mine_existed' => false])
            ->and(file_exists($this->root.'/taken/mine.txt'))->toBeFalse()
            ->and(file_exists($this->root.'/taken/sub'))->toBeFalse()
            ->and(file_get_contents($this->root.'/taken/generated.txt'))->toBe('new')
            ->and($output)->toContain('created successfully');
    })->with(['theme', 'plugin']);

    it('deletes nothing when another name is typed', function (string $kind, string $typed): void {
        mkdir($this->root.'/taken');
        file_put_contents($this->root.'/taken/mine.txt', 'mine');
        $scaffolder = m::mock(ModuleScaffolderService::class);
        $scaffolder->shouldNotReceive('downloadAndScaffold');

        app()->instance(ModuleScaffolderService::class, $scaffolder);
        forceTyping($typed);

        [$code, $output] = forceRun(forceCommand($kind, $this->root), $kind, 'taken', ['--force' => true]);

        expect($code)->toBe(1)
            ->and($output)->toContain('nothing was deleted')
            ->and(file_get_contents($this->root.'/taken/mine.txt'))->toBe('mine');
    })->with([
        'theme: a yes' => ['theme', 'yes'],
        'theme: nothing' => ['theme', ''],
        'theme: another case' => ['theme', 'TAKEN'],
        'plugin: a yes' => ['plugin', 'y'],
        'plugin: a prefix of the name' => ['plugin', 'tak'],
    ]);

    it('never deletes without a terminal, and says to run it interactively', function (string $kind): void {
        mkdir($this->root.'/taken');
        file_put_contents($this->root.'/taken/mine.txt', 'mine');
        $scaffolder = m::mock(ModuleScaffolderService::class);
        $scaffolder->shouldNotReceive('downloadAndScaffold');

        app()->instance(ModuleScaffolderService::class, $scaffolder);
        // Whatever a prompt would answer, nobody is there to be asked.
        forceTyping('taken');

        [$code, $output] = forceRun(forceCommand($kind, $this->root), $kind, 'taken', ['--force' => true], interactive: false);

        expect($code)->toBe(1)
            ->and($output)->toContain('run this command interactively')
            ->and(file_get_contents($this->root.'/taken/mine.txt'))->toBe('mine');
    })->with(['theme', 'plugin']);

    it('refuses to delete the active theme or plugin', function (string $kind, string $active): void {
        mkdir($this->root.'/taken');
        file_put_contents($this->root.'/taken/mine.txt', 'mine');
        $scaffolder = m::mock(ModuleScaffolderService::class);
        $scaffolder->shouldNotReceive('downloadAndScaffold');

        app()->instance(ModuleScaffolderService::class, $scaffolder);
        forceTyping('taken');

        [$code, $output] = forceRun(forceCommand($kind, $this->root, [$active]), $kind, 'taken', ['--force' => true]);

        expect($code)->toBe(1)
            ->and($output)->toContain('is active')
            ->and(file_get_contents($this->root.'/taken/mine.txt'))->toBe('mine');
    })->with([
        'theme' => ['theme', 'taken'],
        'plugin' => ['plugin', 'taken/taken.php'],
    ]);

    it('does not take another plugin whose name starts the same for the active one', function (): void {
        mkdir($this->root.'/taken');
        $seen = null;
        forceScaffolder($seen);
        forceTyping('taken');

        [$code] = forceRun(forceCommand('plugin', $this->root, ['taken-two/taken-two.php']), 'plugin', 'taken', ['--force' => true]);

        expect($code)->toBe(0)->and($seen['target_existed'])->toBeFalse();
    });

    it('refuses a symbolic link and leaves its target alone', function (string $kind): void {
        $outside = forceTempDir();
        file_put_contents($outside.'/precious.txt', 'precious');
        symlink($outside, $this->root.'/linked');
        $scaffolder = m::mock(ModuleScaffolderService::class);
        $scaffolder->shouldNotReceive('downloadAndScaffold');

        app()->instance(ModuleScaffolderService::class, $scaffolder);
        forceTyping('linked');

        try {
            [$code, $output] = forceRun(forceCommand($kind, $this->root), $kind, 'linked', ['--force' => true]);
            $kept = file_exists($outside.'/precious.txt');
        } finally {
            unlink($this->root.'/linked');
            (new Filesystem)->deleteDirectory($outside);
        }

        expect($code)->toBe(1)->and($output)->toContain('symbolic link')->and($kept)->toBeTrue();
    })->with(['theme', 'plugin']);
});

describe('the deletion guards', function (): void {
    /**
     * @return array{0: bool, 1: string}
     */
    function forceReplace(string $name, string $root, bool $active = false): array
    {
        $command = new class extends Command
        {
            use ReplacesExistingDirectory;

            public Filesystem $files;

            public array $errors = [];

            public function option($key = null): mixed
            {
                return true;
            }

            public function error($string, $verbosity = null): void
            {
                $this->errors[] = (string) $string;
            }

            public function run(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            public function replace(string $name, string $root, bool $active): bool
            {
                return $this->replaceExistingDirectory('Theme', $name, $root, $active);
            }
        };
        $command->files = new Filesystem;

        $input = new ArrayInput([]);
        $input->setInteractive(true);
        (fn (): ArrayInput => $this->input = $input)->call($command);
        forceTyping($name);

        return [$command->replace($name, $root, $active), implode("\n", $command->errors)];
    }

    it('refuses a name that is empty or climbs out of the root', function (string $name): void {
        mkdir($this->root.'/inside');
        $outside = dirname($this->root).'/'.basename($this->root).'-outside';
        mkdir($outside);
        file_put_contents($outside.'/keep.txt', 'keep');

        try {
            [$deleted, $errors] = forceReplace($name, $this->root.'/inside');
            $kept = file_exists($outside.'/keep.txt') && is_dir($this->root.'/inside');
        } finally {
            (new Filesystem)->deleteDirectory($outside);
        }

        expect($deleted)->toBeFalse()->and($errors)->toContain('Refusing to delete')->and($kept)->toBeTrue();
    })->with(['', '.', '..', '../'.'x-outside', 'a/b', 'a\\b', '..hidden..']);

    it('refuses a target that is not a directory', function (): void {
        file_put_contents($this->root.'/afile', 'x');

        [$deleted, $errors] = forceReplace('afile', $this->root);

        expect($deleted)->toBeFalse()->and($errors)->toContain('not a directory')->and(file_exists($this->root.'/afile'))->toBeTrue();
    });

    it('refuses when the real path leaves the root', function (): void {
        // The root is reached through a link: the target resolves outside of "<root>/<name>".
        $real = forceTempDir();
        mkdir($real.'/sub');
        file_put_contents($real.'/sub/keep.txt', 'keep');
        mkdir($this->root.'/inside');
        symlink($real, $this->root.'/inside/name');

        try {
            [$deleted, $errors] = forceReplace('name', $this->root.'/inside');
            $kept = file_exists($real.'/sub/keep.txt');
        } finally {
            unlink($this->root.'/inside/name');
            (new Filesystem)->deleteDirectory($real);
        }

        expect($deleted)->toBeFalse()->and($errors)->toContain('symbolic link')->and($kept)->toBeTrue();
    });

    it('deletes a folder directly inside the root once the name is typed', function (): void {
        mkdir($this->root.'/ok');
        file_put_contents($this->root.'/ok/f.txt', 'x');

        [$deleted] = forceReplace('ok', $this->root);

        expect($deleted)->toBeTrue()->and(file_exists($this->root.'/ok'))->toBeFalse();
    });

    it('goes through Filesystem::deleteDirectory', function (): void {
        mkdir($this->root.'/ok');
        $files = m::mock(Filesystem::class);
        $files->shouldReceive('deleteDirectory')->once()->with($this->root.'/ok')->andReturn(true);
        $command = new class extends Command
        {
            use ReplacesExistingDirectory;

            public $files;

            public function option($key = null): mixed
            {
                return true;
            }

            public function run(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            public function replace(string $root): bool
            {
                return $this->replaceExistingDirectory('Theme', 'ok', $root, false);
            }
        };
        $command->files = $files;

        $input = new ArrayInput([]);
        $input->setInteractive(true);
        (fn (): ArrayInput => $this->input = $input)->call($command);
        (fn (): OutputStyle => $this->output = new OutputStyle($input, new BufferedOutput))->call($command);
        forceTyping('ok');

        // The mock does not delete anything: the command then reports the folder as still there.
        expect($command->replace($this->root))->toBeFalse();
    });
});

describe('a folder named like the template repository', function (): void {
    it('is deleted before the archive is downloaded', function (): void {
        // The downloader extracts into "<themes>/<repository name>": that is the folder of this theme.
        // The old content must be gone first, or the new files would land among the old ones.
        $zip = new ZipArchive;
        $archive = $this->root.'/archive.zip';
        $zip->open($archive, ZipArchive::CREATE);
        $zip->addFromString('theme-default-1.0.0/style.css', '/* new */');
        $zip->close();
        Http::fake(['github.com/pollora/theme-default/archive/*' => Http::response(file_get_contents($archive)), 'api.github.com/*' => Http::response([['name' => 'v1.0.0']])]);
        unlink($archive);
        $themes = $this->root.'/themes';
        mkdir($themes.'/theme-default', 0777, true);
        file_put_contents($themes.'/theme-default/mine.txt', 'mine');
        app()->instance(ModuleScaffolderService::class, new ModuleScaffolderService);
        forceTyping('theme-default');

        [, $output] = forceRun(forceCommand('theme', $themes), 'theme', 'theme-default', ['--force' => true, '--repository' => 'pollora/theme-default']);

        expect($output)->toContain('Downloaded and extracted successfully.')
            ->and(file_exists($themes.'/theme-default/mine.txt'))->toBeFalse();
    });
});
