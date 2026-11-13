<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Console\OutputStyle;
use Illuminate\Filesystem\Filesystem;
use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\TextPrompt;
use Mockery as m;
use Pollora\Modules\Infrastructure\Services\ModuleScaffolderService;
use Pollora\Theme\Application\Services\ThemeAvailability;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;
use Pollora\Theme\Domain\Contracts\ThemeService;
use Pollora\Theme\Domain\Models\ThemeMetadata;
use Pollora\Theme\UI\Console\Commands\ThemeStatusCommand;
use Pollora\Theme\UI\Console\MakeThemeCommand;
use Pollora\Theme\UI\Console\RemoveThemeCommand;
use Pollora\Translation\Domain\Contracts\TranslationCompilerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function consoleTempDir(): string
{
    $dir = sys_get_temp_dir().'/pollora-console-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);

    return $dir;
}

function consoleRemove(string $dir): void
{
    (new Filesystem)->deleteDirectory($dir);
}

/**
 * @return array{0: int, 1: string}
 */
function consoleRun(Illuminate\Console\Command $command, array $parameters = [], bool $interactive = false): array
{
    $command->setLaravel(app());
    $input = new ArrayInput($parameters);
    $input->setInteractive($interactive);
    $output = new BufferedOutput;
    $code = $command->run($input, $output);

    return [$code, $output->fetch()];
}

function consoleTheme(string $name, bool $enabled = true): ThemeModuleInterface
{
    $theme = new Pollora\Theme\Domain\Models\ThemeModule($name, '/themes/'.$name);
    $theme->setEnabled($enabled);

    return $theme;
}

function makeThemeCommand(string $themesDir, array $config = []): MakeThemeCommand
{
    $command = new class(new Repository(['theme' => ['directory' => $themesDir, ...$config]]), new Filesystem) extends MakeThemeCommand
    {
        public int $npmRuns = 0;

        protected function runNpmIfNeeded(): void
        {
            $this->npmRuns++;
        }
    };
    $command->setDefinition((new MakeThemeCommand(new Repository, new Filesystem))->getDefinition());

    return $command;
}

describe('pollora:theme:status', function (): void {
    function consoleStatus(?ThemeModuleInterface $registered, ?ThemeModuleInterface $active): string
    {
        $registrar = m::mock(ThemeRegistrarInterface::class);
        $registrar->shouldReceive('getActiveTheme')->andReturn($registered);
        $service = m::mock(ThemeService::class);
        $service->shouldReceive('getActiveTheme')->andReturn($active);
        app()->instance(ThemeRegistrarInterface::class, $registrar);
        app()->instance(ThemeService::class, $service);

        [$code, $output] = consoleRun(new ThemeStatusCommand);

        expect($code)->toBe(0);

        return $output;
    }

    it('says so when no theme is registered anywhere', function (): void {
        $output = consoleStatus(null, null);

        expect($output)->toContain('No self-registered theme found')
            ->and($output)->toContain('No active theme found via ThemeService');
    });

    it('lists the registered theme, its headers, and that the service is in sync', function (): void {
        $theme = consoleTheme('demo')->setHeaders(['Name' => 'Demo Theme', 'Version' => '2.0']);
        $output = consoleStatus($theme, $theme);

        expect($output)->toContain('Self-registered theme found')
            ->and($output)->toContain('Name: demo')
            ->and($output)->toContain('Path: /themes/demo')
            ->and($output)->toContain('Enabled: Yes')
            ->and($output)->toContain('Display Name: Demo Theme')
            ->and($output)->toContain('Version: 2.0')
            ->and($output)->toContain('Theme service and registrar are in sync');
    });

    it('flags a service whose active theme differs from the registered one', function (): void {
        $output = consoleStatus(consoleTheme('one', false), consoleTheme('two'));

        expect($output)->toContain('Enabled: No')
            ->and($output)->toContain('Theme service and registrar are out of sync');
    });
});

describe('pollora:theme:delete', function (): void {
    beforeEach(function (): void {
        $this->dir = consoleTempDir();
        $this->public = consoleTempDir();
        app()->usePublicPath($this->public);
    });

    afterEach(function (): void {
        consoleRemove($this->dir);
        consoleRemove($this->public);
    });

    function removeCommand(string $themes, bool $confirm, array $config = []): RemoveThemeCommand
    {
        $command = new class(new Repository(['theme' => ['directory' => $themes, ...$config]]), new Filesystem, $confirm) extends RemoveThemeCommand
        {
            public function __construct(Repository $config, Filesystem $files, private readonly bool $answer)
            {
                parent::__construct($config, $files);
            }

            public function confirm($question, $default = false): bool
            {
                return $this->answer;
            }
        };
        $command->setDefinition((new RemoveThemeCommand(new Repository, new Filesystem))->getDefinition());

        return $command;
    }

    it('fails when the theme does not exist', function (): void {
        [$code, $output] = consoleRun(removeCommand($this->dir, true), ['name' => 'ghost']);

        expect($code)->toBe(1)->and($output)->toContain('Theme "ghost" does not exist.');
    });

    it('deletes the theme directory and its built assets once confirmed', function (): void {
        mkdir($this->dir.'/demo/resources', 0777, true);
        file_put_contents($this->dir.'/demo/style.css', '');
        mkdir($this->public.'/themes/demo', 0777, true);
        file_put_contents($this->public.'/themes/demo/app.js', '');

        [$code, $output] = consoleRun(removeCommand($this->dir, true), ['name' => 'demo']);

        expect($code)->toBe(0)
            ->and($output)->toContain('Theme "demo" has been removed successfully.')
            ->and(is_dir($this->dir.'/demo'))->toBeFalse()
            ->and(is_dir($this->public.'/themes/demo'))->toBeFalse();
    });

    it('removes the theme even when it has no built assets, using the configured assets path', function (): void {
        mkdir($this->dir.'/demo', 0777, true);
        mkdir($this->public.'/build/demo', 0777, true);

        [$code] = consoleRun(removeCommand($this->dir, true, ['assets_path' => 'build']), ['name' => 'demo']);

        expect($code)->toBe(0)
            ->and(is_dir($this->dir.'/demo'))->toBeFalse()
            ->and(is_dir($this->public.'/build/demo'))->toBeFalse();
    });

    it('keeps everything when the deletion is not confirmed', function (): void {
        mkdir($this->dir.'/demo', 0777, true);

        [$code, $output] = consoleRun(removeCommand($this->dir, false), ['name' => 'demo']);

        expect($code)->toBe(0)
            ->and($output)->toContain('Theme removal cancelled.')
            ->and(is_dir($this->dir.'/demo'))->toBeTrue();
    });
});

describe('pollora:make:theme', function (): void {
    beforeEach(function (): void {
        $this->dir = consoleTempDir();
        Prompt::fallbackWhen(true);
    });

    afterEach(function (): void {
        consoleRemove($this->dir);
        Prompt::fallbackWhen(false);
    });

    function makeThemeScaffolder(bool $succeeds, ?Closure $inspect = null): ModuleScaffolderService
    {
        $scaffolder = m::mock(ModuleScaffolderService::class);
        $scaffolder->shouldReceive('downloadAndScaffold')->andReturnUsing(function (...$args) use ($succeeds, $inspect): bool {
            if ($inspect instanceof Closure) {
                $inspect($args);
            }

            return $succeeds;
        });
        app()->instance(ModuleScaffolderService::class, $scaffolder);

        return $scaffolder;
    }

    it('rejects an invalid theme name before downloading anything', function (): void {
        $scaffolder = m::mock(ModuleScaffolderService::class);
        $scaffolder->shouldNotReceive('downloadAndScaffold');
        app()->instance(ModuleScaffolderService::class, $scaffolder);

        [$code, $output] = consoleRun(makeThemeCommand($this->dir), ['name' => 'bad/name']);

        expect($code)->toBe(1)->and($output)->toContain('Name must be alphanumeric, dash, space or underscore.');
    });

    it('refuses to overwrite an existing theme when nobody confirms', function (): void {
        mkdir($this->dir.'/taken');
        $scaffolder = m::mock(ModuleScaffolderService::class);
        $scaffolder->shouldNotReceive('downloadAndScaffold');
        app()->instance(ModuleScaffolderService::class, $scaffolder);

        [$code, $output] = consoleRun(makeThemeCommand($this->dir), ['name' => 'taken']);

        expect($code)->toBe(1)->and($output)->toContain('Theme "taken" already exists.');
    });

    it('downloads the chosen repository into the themes directory with the theme replacements', function (): void {
        $received = [];
        makeThemeScaffolder(true, function (array $args) use (&$received): void {
            $received = array_combine(['repository', 'basePath', 'targetPath', 'replacements', 'version', 'output', 'fileFilter', 'removeDirs'], array_pad($args, 8, null));
        });

        $command = makeThemeCommand($this->dir);
        [$code, $output] = consoleRun($command, [
            'name' => 'my-journal',
            '--repository' => 'acme/theme-x',
            '--repo-version' => 'v2.1.0',
            '--theme-author' => 'Ann',
            '--theme-version' => '3.4',
            '--no-activate' => true,
        ]);

        expect($code)->toBe(0)
            ->and($output)->toContain('Theme "my-journal" created successfully.')
            ->and($received['repository'])->toBe('acme/theme-x')
            ->and($received['basePath'])->toBe($this->dir)
            ->and($received['targetPath'])->toBe($this->dir.'/my-journal')
            ->and($received['version'])->toBe('v2.1.0')
            ->and($received['removeDirs'])->toBe(['bin'])
            ->and($received['replacements'])->toMatchArray([
                '%theme_name%' => 'my-journal',
                '%theme_camel%' => 'myJournal',
                '%theme_author%' => 'Ann',
                '%theme_version%' => '3.4',
                '%theme_namespace%' => 'Theme\\MyJournal',
            ])
            ->and($command->npmRuns)->toBe(1);
    });

    it('explains the failure when the download fails and no template is bundled', function (): void {
        makeThemeScaffolder(false);
        $command = makeThemeCommand($this->dir);

        [$code, $output] = consoleRun($command, ['name' => 'nope', '--repository' => 'a/b']);

        expect($code)->toBe(1)
            ->and($output)->toContain('The theme could not be downloaded')
            ->and($output)->toContain('Check the network and the repository')
            ->and($command->npmRuns)->toBe(0);
    });

    it('falls back to the default template repository when the prompt returns nothing', function (): void {
        $received = [];
        makeThemeScaffolder(true, function (array $args) use (&$received): void {
            $received = array_combine(['repository', 'basePath', 'targetPath', 'replacements', 'version', 'output', 'fileFilter', 'removeDirs'], array_pad($args, 8, null));
        });
        $command = new class(new Repository(['theme' => ['directory' => $this->dir]]), new Filesystem) extends MakeThemeCommand
        {
            protected function promptForRepository(): ?string
            {
                return '';
            }

            protected function runNpmIfNeeded(): void {}
        };
        $command->setDefinition((new MakeThemeCommand(new Repository, new Filesystem))->getDefinition());

        consoleRun($command, ['name' => 'plain', '--no-activate' => true]);

        expect($received['repository'])->toBe('pollora/theme-default');
    });

    it('validates names: required, charset and uniqueness', function (): void {
        mkdir($this->dir.'/taken');
        $command = makeThemeCommand($this->dir);
        $validate = fn (string $value): ?string => (fn () => $this->validateValue($value))->call($command);

        expect($validate(''))->toBe('Name is required.')
            ->and($validate('0'))->toBe('Name is required.')
            ->and($validate('a.b'))->toBe('Name must be alphanumeric, dash, space or underscore.')
            ->and($validate('taken'))->toBe('Theme "taken" already exists.')
            ->and($validate('fresh_theme-2'))->toBeNull();
    });

    it('maps theme.structure to container folders with defaults', function (): void {
        $command = makeThemeCommand($this->dir, ['structure' => ['assets' => 'src/assets', 'views' => 'tpl']]);
        $folders = (function () {
            $this->setupContainerFolders();

            return $this->containerFolder;
        })->call($command);

        expect($folders)->toBe([
            'assets' => 'src/assets',
            'lang' => 'lang',
            'layout' => 'resources/views/layouts',
            'partial' => 'resources/views/partials',
            'view' => 'tpl',
        ]);
    });

    it('offers prompts with defaults for the theme options', function (): void {
        $command = makeThemeCommand($this->dir);
        $options = (fn () => $this->promptForMissingOptionsUsing())->call($command);

        expect(array_keys($options))->toBe(['theme-author', 'theme-author-uri', 'theme-uri', 'theme-description', 'theme-version'])
            ->and($options['theme-author']['default'])->toBe('Pollora')
            ->and($options['theme-version']['default'])->toBe('1.0');
    });

    it('prompts for a missing name with the default theme name', function (): void {
        TextPrompt::fallbackUsing(fn (TextPrompt $prompt): string => $prompt->default);
        $command = makeThemeCommand($this->dir);
        $arguments = (fn () => $this->promptForMissingArgumentsUsing())->call($command);

        expect($arguments['name']())->toBe('default');
    });

    it('compiles the translations of a freshly generated theme', function (): void {
        mkdir($this->dir.'/t/languages', 0777, true);
        file_put_contents($this->dir.'/t/languages/fr.po', '');
        $compiler = m::mock(TranslationCompilerInterface::class);
        $compiler->shouldReceive('compileDirectory')->once()->with($this->dir.'/t/languages')->andReturn(2);
        app()->instance(TranslationCompilerInterface::class, $compiler);
        $command = makeThemeCommand($this->dir);
        $command->setLaravel(app());
        $output = new BufferedOutput;
        $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
        (new ReflectionProperty($command, 'theme'))->setValue($command, new ThemeMetadata('t', $this->dir));

        (fn () => $this->compileTranslations())->call($command);

        expect($output->fetch())->toContain('Compiled 2 translation file(s).');
    });

    it('does not compile anything without .po files', function (): void {
        mkdir($this->dir.'/t/languages', 0777, true);
        $compiler = m::mock(TranslationCompilerInterface::class);
        $compiler->shouldNotReceive('compileDirectory');
        app()->instance(TranslationCompilerInterface::class, $compiler);
        $command = makeThemeCommand($this->dir);
        $command->setLaravel(app());
        (new ReflectionProperty($command, 'theme'))->setValue($command, new ThemeMetadata('t', $this->dir));

        $result = (fn () => $this->compileTranslations())->call($command);

        expect($result)->toBeNull();
    });
});

describe('pollora:make:theme requirements', function (): void {
    beforeEach(function (): void {
        $this->dir = consoleTempDir();
        Prompt::fallbackWhen(true);
        $this->command = makeThemeCommand($this->dir);
        $this->command->setLaravel(app());
        $this->output = new BufferedOutput;
        $this->command->setOutput(new OutputStyle(new ArrayInput([]), $this->output));
        (new ReflectionProperty($this->command, 'theme'))->setValue($this->command, new ThemeMetadata('t', $this->dir));
        mkdir($this->dir.'/t', 0777, true);
    });

    afterEach(function (): void {
        consoleRemove($this->dir);
        Prompt::fallbackWhen(false);
    });

    it('does nothing when the theme ships no requirements.json', function (): void {
        (fn () => $this->installRequirementsIfNeeded())->call($this->command);

        expect($this->output->fetch())->toBe('');
    });

    it('does nothing when the requirements list no composer package', function (): void {
        file_put_contents($this->dir.'/t/requirements.json', json_encode(['composer' => []]));

        (fn () => $this->installRequirementsIfNeeded())->call($this->command);

        expect($this->output->fetch())->toBe('');
    });

    it('offers the required packages and skips the installation when none is selected', function (): void {
        file_put_contents($this->dir.'/t/requirements.json', json_encode(['composer' => ['acme/plugin' => 'A plugin']]));
        $offered = null;
        MultiSelectPrompt::fallbackUsing(function (MultiSelectPrompt $prompt) use (&$offered): array {
            $offered = [$prompt->options, $prompt->default];

            return [];
        });

        (fn () => $this->installRequirementsIfNeeded())->call($this->command);

        expect($this->output->fetch())->toContain('This theme requires the following Composer packages:')
            ->and($offered)->toBe([['acme/plugin' => 'acme/plugin — A plugin'], ['acme/plugin']]);
    });
});

describe('pollora:make:theme plugins and activation', function (): void {
    beforeEach(function (): void {
        Prompt::fallbackWhen(true);
        $this->command = makeThemeCommand(sys_get_temp_dir());
        $this->command->setLaravel(app());
        $this->output = new BufferedOutput;
        $this->command->setOutput(new OutputStyle(new ArrayInput([]), $this->output));
    });

    afterEach(function (): void {
        Prompt::fallbackWhen(false);
    });

    it('finds the conventional entry file of a plugin, then falls back to scanning for the header', function (): void {
        // WP_PLUGIN_DIR is a constant other tests define differently, so the lookup runs in its own process.
        $pluginsDir = sys_get_temp_dir().'/pollora-plugins-'.bin2hex(random_bytes(6));
        mkdir($pluginsDir.'/conventional', 0777, true);
        mkdir($pluginsDir.'/fallback');
        mkdir($pluginsDir.'/headerless');
        file_put_contents($pluginsDir.'/conventional/conventional.php', "<?php\n/* Plugin Name: Conv */");
        file_put_contents($pluginsDir.'/fallback/helper.php', '<?php // no header');
        file_put_contents($pluginsDir.'/fallback/main.php', "<?php\n/* Plugin Name: Main */");
        file_put_contents($pluginsDir.'/headerless/readme.php', '<?php // nothing');

        $script = <<<'PHP'
            require $argv[1].'/vendor/autoload.php';
            define('WP_PLUGIN_DIR', $argv[2]);
            $command = new class(new Illuminate\Config\Repository, new Illuminate\Filesystem\Filesystem) extends Pollora\Theme\UI\Console\MakeThemeCommand {
                public function find(string $slug): ?string { return $this->findPluginEntryFile($slug); }
            };
            echo json_encode(array_map($command->find(...), ['conventional', 'fallback', 'headerless', 'missing']));
            PHP;

        try {
            $process = new Symfony\Component\Process\Process([PHP_BINARY, '-r', $script, dirname(__DIR__, 3), $pluginsDir]);
            $process->run();
            $found = json_decode($process->getOutput(), true);
        } finally {
            consoleRemove($pluginsDir);
        }

        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
            ->and($found)->toBe(['conventional/conventional.php', 'fallback/main.php', null, null]);
    });

    it('only keeps packages Composer knows as WordPress plugins', function (): void {
        $plugins = (fn () => $this->resolveWordPressPlugins(['phpunit/phpunit', 'not/installed']))->call($this->command);

        expect($plugins)->toBe([]);
    });

    it('asks to replace the active theme and follows the answer', function (): void {
        $registrar = m::mock(ThemeRegistrarInterface::class);
        $registrar->shouldReceive('getActiveTheme')->andReturn(consoleTheme('other'));
        $container = new Illuminate\Container\Container;
        $container->instance(ThemeRegistrarInterface::class, $registrar);
        app()->instance(ThemeAvailability::class, new ThemeAvailability($container));
        $this->command->setInput(tap(new ArrayInput(['name' => 'x'], $this->command->getDefinition()), fn ($input) => $input->setInteractive(true)));
        $ask = fn (): bool => (fn () => $this->shouldActivateTheme('mine'))->call($this->command);

        SelectPrompt::fallbackUsing(fn (): string => 'yes');
        $yes = $ask();
        SelectPrompt::fallbackUsing(fn (): string => 'no');
        $no = $ask();

        expect($yes)->toBeTrue()->and($no)->toBeFalse();
    });

    it('prefers the --repository option over any prompt', function (): void {
        $command = makeThemeCommand(sys_get_temp_dir());
        $command->setInput(new ArrayInput(['name' => 'x', '--repository' => 'acme/own'], (new MakeThemeCommand(new Repository, new Filesystem))->getDefinition()));

        expect((fn () => $this->promptForRepository())->call($command))->toBe('acme/own');
    });

    it('maps each template choice to its repository and validates a custom one', function (): void {
        $command = makeThemeCommand(sys_get_temp_dir());
        $command->setInput(new ArrayInput(['name' => 'x'], (new MakeThemeCommand(new Repository, new Filesystem))->getDefinition()));
        $choose = function (string $choice) use ($command): ?string {
            SelectPrompt::fallbackUsing(fn (): string => $choice);

            return (fn () => $this->promptForRepository())->call($command);
        };

        expect($choose('default'))->toBe('pollora/theme-default')
            ->and($choose('ecommerce'))->toBe('pollora/theme-apiary')
            ->and($choose('magazine'))->toBe('pollora/theme-buzz');

        $validation = [];
        TextPrompt::fallbackUsing(function (TextPrompt $prompt) use (&$validation): string {
            $validation = [
                ($prompt->validate)(''),
                ($prompt->validate)('no-slash'),
                ($prompt->validate)('owner/repo'),
            ];

            return 'owner/repo';
        });

        expect($choose('repository'))->toBe('owner/repo')
            ->and($validation)->toBe(['Repository is required', 'Repository must be in owner/repo format', null]);
    });
});

describe('pollora:make:theme plugin activation branches', function (): void {
    function pluginCommand(array $resolved, array $entryFiles, bool $canActivate = true, bool $processSucceeds = true, mixed $activationResult = null): object
    {
        $command = new class(new Repository, new Filesystem) extends MakeThemeCommand
        {
            public array $resolved = [];

            public array $entryFiles = [];

            public bool $canActivate = true;

            public bool $processSucceeds = true;

            public mixed $activationResult = null;

            public array $activated = [];

            public array $processes = [];

            protected function wordPressCanActivatePlugins(): bool
            {
                return $this->canActivate;
            }

            protected function resolveWordPressPlugins(array $packages): array
            {
                return $this->resolved;
            }

            protected function findPluginEntryFile(string $slug): ?string
            {
                return $this->entryFiles[$slug] ?? null;
            }

            protected function activatePlugin(string $pluginFile): mixed
            {
                $this->activated[] = $pluginFile;

                return $this->activationResult;
            }

            protected function runInNewProcess(string $command, array $arguments): bool
            {
                $this->processes[] = [$command, $arguments];

                return $this->processSucceeds;
            }

            public BufferedOutput $buffer;

            public function activate(array $packages): void
            {
                $this->activateWordPressPlugins($packages);
            }
        };
        $command->resolved = $resolved;
        $command->entryFiles = $entryFiles;
        $command->canActivate = $canActivate;
        $command->processSucceeds = $processSucceeds;
        $command->activationResult = $activationResult;
        $command->setLaravel(app());
        $input = new ArrayInput([], new Symfony\Component\Console\Input\InputDefinition);
        $input->setInteractive(false);
        $command->setInput($input);
        $command->buffer = $buffer = new BufferedOutput;
        $command->setOutput(new OutputStyle($input, $buffer));

        return $command;
    }

    beforeEach(function (): void {
        Prompt::fallbackWhen(true);
        Laravel\Prompts\ConfirmPrompt::fallbackUsing(fn (): bool => true);
        Brain\Monkey\Functions\when('did_action')->justReturn(1);
    });

    afterEach(function (): void {
        Prompt::fallbackWhen(false);
    });

    it('does not look for plugins when WordPress cannot activate them', function (): void {
        $command = pluginCommand(['a/b' => 'b'], ['b' => 'b/b.php'], canActivate: false);

        $command->activate(['a/b']);

        expect($command->activated)->toBe([])->and($command->processes)->toBe([]);
    });

    it('does nothing when none of the installed packages is a WordPress plugin', function (): void {
        $command = pluginCommand([], []);

        $command->activate(['a/b']);

        expect($command->activated)->toBe([])->and($command->buffer->fetch())->toBe('');
    });

    it('asks once for a single plugin and by count for several, and activates nothing when declined', function (): void {
        $labels = [];
        Laravel\Prompts\ConfirmPrompt::fallbackUsing(function (Laravel\Prompts\ConfirmPrompt $prompt) use (&$labels): bool {
            $labels[] = $prompt->label;

            return false;
        });
        $single = pluginCommand(['wpackagist-plugin/woocommerce' => 'woocommerce'], ['woocommerce' => 'woocommerce/woocommerce.php']);
        $several = pluginCommand(['a/one' => 'one', 'a/two' => 'two'], ['one' => 'one/one.php', 'two' => 'two/two.php']);

        $single->activate(['wpackagist-plugin/woocommerce']);
        $several->activate(['a/one', 'a/two']);

        expect($labels)->toBe(['Activate the WordPress plugin "woocommerce"?', 'Activate 2 WordPress plugins?'])
            ->and($single->activated)->toBe([])
            ->and($several->activated)->toBe([]);
    });

    it('activates the plugins it finds and warns about the one without entry file', function (): void {
        $command = pluginCommand(['a/one' => 'one', 'a/lost' => 'lost'], ['one' => 'one/one.php']);

        $command->activate(['a/one', 'a/lost']);
        $output = $command->buffer->fetch();

        expect($command->activated)->toBe(['one/one.php'])
            ->and($output)->toContain('Could not find entry file for plugin "lost".')
            ->and($output)->toContain('Plugin "one" activated.');
    });

    it('reports the WordPress error when a plugin fails to activate', function (): void {
        $error = new class extends WP_Error
        {
            public function get_error_message(): string
            {
                return 'Fatal in plugin';
            }
        };
        $command = pluginCommand(['a/one' => 'one'], ['one' => 'one/one.php'], activationResult: $error);

        $command->activate(['a/one']);

        expect($command->buffer->fetch())->toContain('Failed to activate "one": Fatal in plugin');
    });

    it('tells how to activate the plugins by hand when the new process fails', function (): void {
        Brain\Monkey\Functions\when('did_action')->justReturn(0);
        $command = pluginCommand(['a/one' => 'one', 'a/lost' => 'lost'], ['one' => 'one/one.php'], processSucceeds: false);

        $command->activate(['a/one', 'a/lost']);
        $output = $command->buffer->fetch();

        expect($command->processes)->toBe([['pollora:plugin:activate', ['one/one.php']]])
            ->and($output)->toContain('Could not find entry file for plugin "lost".')
            ->and($output)->toContain('The plugins could not be activated now.')
            ->and($output)->toContain('wp plugin activate one');
    });

    it('starts no process when no plugin has an entry file', function (): void {
        Brain\Monkey\Functions\when('did_action')->justReturn(0);
        $command = pluginCommand(['a/lost' => 'lost'], []);

        $command->activate(['a/lost']);

        expect($command->processes)->toBe([]);
    });
});

describe('pollora:make:theme default translation compiler', function (): void {
    it('compiles the .po files itself when no compiler is bound', function (): void {
        $dir = consoleTempDir();
        mkdir($dir.'/t/languages', 0777, true);
        file_put_contents($dir.'/t/languages/fr.po', "msgid \"\"\nmsgstr \"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"Hello\"\nmsgstr \"Bonjour\"\n");
        $command = makeThemeCommand($dir);
        $command->setLaravel(app());
        $output = new BufferedOutput;
        $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
        (new ReflectionProperty($command, 'theme'))->setValue($command, new ThemeMetadata('t', $dir));

        try {
            (fn () => $this->compileTranslations())->call($command);
            $compiled = file_exists($dir.'/t/languages/fr.mo');
        } finally {
            consoleRemove($dir);
        }

        expect($compiled)->toBeTrue()->and($output->fetch())->toContain('Compiled 1 translation file(s).');
    });
});
