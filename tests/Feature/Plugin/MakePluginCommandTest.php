<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Console\OutputStyle;
use Illuminate\Filesystem\Filesystem;
use Pollora\Modules\Infrastructure\Services\ModuleScaffolderService;
use Pollora\Plugin\UI\Console\MakePluginCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * pollora:make:plugin writing under a directory of the test, whatever WP_PLUGIN_DIR says.
 *
 * An anonymous subclass loses the #[Signature] of the command, so it takes the definition
 * of the real one.
 */
function makePluginCommandIn(string $pluginsPath, ModuleScaffolderService $scaffolder, ?string $template = null): MakePluginCommand
{
    $command = new class(new Repository, new Filesystem, $scaffolder, $pluginsPath, $template) extends MakePluginCommand
    {
        public function __construct(Repository $config, Filesystem $files, ModuleScaffolderService $scaffolder, private readonly string $pluginsPath, private readonly ?string $template)
        {
            parent::__construct($config, $files, $scaffolder);
        }

        protected function getPluginsPath(): string
        {
            return $this->pluginsPath;
        }

        protected function getTemplatePath(string $templateName): ?string
        {
            return $this->template;
        }
    };

    $command->setDefinition((new MakePluginCommand(new Repository, new Filesystem, $scaffolder))->getDefinition());
    $command->setLaravel(app());

    return $command;
}

/**
 * @return array{0: int, 1: string}
 */
function runMakePlugin(MakePluginCommand $command, array $input): array
{
    $input = new ArrayInput($input);
    $input->setInteractive(false);

    $output = new BufferedOutput;

    return [$command->run($input, $output), $output->fetch()];
}

function makePluginProtected(MakePluginCommand $command, string $method, mixed ...$arguments): mixed
{
    return (new ReflectionMethod($command, $method))->invoke($command, ...$arguments);
}

function makePluginPrepared(MakePluginCommand $command, string $name, array $options = []): MakePluginCommand
{
    $input = new ArrayInput(['name' => $name, ...$options], $command->getDefinition());
    $input->setInteractive(false);

    $command->setInput($input);
    $command->setOutput(new OutputStyle($input, new BufferedOutput));
    (new ReflectionProperty($command, 'plugin'))->setValue($command, (new ReflectionMethod($command, 'makePlugin'))->invoke($command, $name));

    return $command;
}

beforeEach(function (): void {
    $this->pluginsPath = sys_get_temp_dir().'/pollora-make-plugin-cmd-'.uniqid();
    mkdir($this->pluginsPath, 0755, true);
    $this->scaffolder = Mockery::mock(ModuleScaffolderService::class);
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->pluginsPath);
});

describe('handle()', function (): void {
    it('scaffolds the plugin from the given repository into the plugins directory', function (): void {
        $received = [];
        $this->scaffolder->shouldReceive('downloadAndScaffold')->once()->andReturnUsing(function (...$arguments) use (&$received): bool {
            $received = $arguments;

            return true;
        });

        [$status, $output] = runMakePlugin(makePluginCommandIn($this->pluginsPath, $this->scaffolder), [
            'name' => 'acme-shop',
            '--repository' => 'acme/starter',
            '--repo-version' => 'v2.1.0',
            '--activate-plugin' => 'no',
            '--plugin-author' => 'Acme Inc',
        ]);

        expect($status)->toBe(0)
            ->and($output)->toContain('Plugin acme-shop created successfully at '.$this->pluginsPath.'/acme-shop')
            ->and($received[0])->toBe('acme/starter')
            ->and($received[1])->toBe($this->pluginsPath)
            ->and($received[2])->toBe($this->pluginsPath.'/acme-shop')
            ->and($received[4])->toBe('v2.1.0')
            ->and($received[3]['%plugin_author%'])->toBe('Acme Inc');
    });

    it('says the assets are skipped when they are not asked for', function (): void {
        $this->scaffolder->shouldReceive('downloadAndScaffold')->andReturnTrue();

        [, $output] = runMakePlugin(makePluginCommandIn($this->pluginsPath, $this->scaffolder), [
            'name' => 'acme-shop', '--repository' => 'acme/starter', '--activate-plugin' => 'no',
        ]);

        expect($output)->toContain('Assets disabled, skipping npm install/build.');
    });

    it('fails, and says why, when the download fails and no template is bundled', function (): void {
        $this->scaffolder->shouldReceive('downloadAndScaffold')->andReturnFalse();
        $this->scaffolder->shouldNotReceive('copyDirectory');

        [$status, $output] = runMakePlugin(makePluginCommandIn($this->pluginsPath, $this->scaffolder), [
            'name' => 'acme-shop', '--repository' => 'acme/starter',
        ]);

        expect($status)->toBe(1)
            ->and($output)->toContain('this Pollora version bundles no local template')
            ->and($output)->not->toContain('created successfully');
    });

    it('falls back to the bundled template when the download fails', function (): void {
        $this->scaffolder->shouldReceive('downloadAndScaffold')->andReturnFalse();
        $this->scaffolder->shouldReceive('copyDirectory')->once()->with('/templates/common', $this->pluginsPath.'/acme-shop');

        [$status, $output] = runMakePlugin(makePluginCommandIn($this->pluginsPath, $this->scaffolder, template: '/templates/common'), [
            'name' => 'acme-shop', '--repository' => 'acme/starter', '--activate-plugin' => 'no',
        ]);

        expect($status)->toBe(0)
            ->and($output)->toContain('created successfully');
    });

    it('refuses a name with characters a plugin directory should not have', function (): void {
        $this->scaffolder->shouldNotReceive('downloadAndScaffold');

        [$status, $output] = runMakePlugin(makePluginCommandIn($this->pluginsPath, $this->scaffolder), [
            'name' => 'acme/shop!', '--repository' => 'acme/starter',
        ]);

        expect($status)->toBe(1)
            ->and($output)->toContain('Name must be alphanumeric, dash, space or underscore.');
    });

    it('refuses to overwrite an existing plugin', function (): void {
        mkdir($this->pluginsPath.'/acme-shop');
        $this->scaffolder->shouldNotReceive('downloadAndScaffold');

        [$status, $output] = runMakePlugin(makePluginCommandIn($this->pluginsPath, $this->scaffolder), [
            'name' => 'acme-shop', '--repository' => 'acme/starter',
        ]);

        expect($status)->toBe(1)
            ->and($output)->toContain('Plugin "acme-shop" already exists.');
    });
});

describe('validateValue()', function (): void {
    it('accepts letters, digits, dashes, underscores and spaces', function (string $name): void {
        $command = makePluginCommandIn($this->pluginsPath, $this->scaffolder);

        expect(makePluginProtected($command, 'validateValue', $name))->toBeNull();
    })->with(['acme', 'acme-shop', 'Acme_Shop', 'acme shop 2']);

    it('requires a name', function (string $name): void {
        $command = makePluginCommandIn($this->pluginsPath, $this->scaffolder);

        expect(makePluginProtected($command, 'validateValue', $name))->toBe('Name is required.');
    })->with(['', '0']);

    it('rejects other characters', function (string $name): void {
        $command = makePluginCommandIn($this->pluginsPath, $this->scaffolder);

        expect(makePluginProtected($command, 'validateValue', $name))->toBe('Name must be alphanumeric, dash, space or underscore.');
    })->with(['acme.shop', 'acme/shop', 'acme!', 'acmé']);

    it('rejects a name that is already a plugin directory', function (): void {
        mkdir($this->pluginsPath.'/taken');
        $command = makePluginCommandIn($this->pluginsPath, $this->scaffolder);

        expect(makePluginProtected($command, 'validateValue', 'taken'))->toBe('Plugin "taken" already exists.');
    });
});

describe('canGeneratePlugin()', function (): void {
    it('allows a plugin whose directory does not exist', function (): void {
        $command = makePluginPrepared(makePluginCommandIn($this->pluginsPath, $this->scaffolder), 'fresh');

        expect(makePluginProtected($command, 'canGeneratePlugin'))->toBeTrue();
    });

    it('refuses an existing plugin directory without --force', function (): void {
        mkdir($this->pluginsPath.'/taken');
        $command = makePluginPrepared(makePluginCommandIn($this->pluginsPath, $this->scaffolder), 'taken');

        expect(makePluginProtected($command, 'canGeneratePlugin'))->toBeFalse();
    });
});

describe('options', function (): void {
    it('includes the assets for a truthy --asset value only', function (?string $value, bool $expected): void {
        $options = $value === null ? [] : ['--asset' => $value];
        $command = makePluginPrepared(makePluginCommandIn($this->pluginsPath, $this->scaffolder), 'acme', $options);

        expect(makePluginProtected($command, 'shouldIncludeAssets'))->toBe($expected);
    })->with([
        'absent' => [null, false],
        'true' => ['true', true],
        'TRUE' => ['TRUE', true],
        'yes' => ['yes', true],
        'on' => ['on', true],
        '1' => ['1', true],
        'false' => ['false', false],
        'no' => ['no', false],
    ]);

    it('activates unless the option says no', function (?string $value, bool $expected): void {
        $options = $value === null ? [] : ['--activate-plugin' => $value];
        $command = makePluginPrepared(makePluginCommandIn($this->pluginsPath, $this->scaffolder), 'acme', $options);

        expect(makePluginProtected($command, 'shouldActivate'))->toBe($expected);
    })->with([
        'absent' => [null, true],
        'yes' => ['yes', true],
        'true' => ['true', true],
        'no' => ['no', false],
        'false' => ['false', false],
    ]);

    it('offers sensible defaults for the questions it asks', function (): void {
        $command = makePluginCommandIn($this->pluginsPath, $this->scaffolder);

        $options = makePluginProtected($command, 'promptForMissingOptionsUsing');

        expect($options['plugin-version']['default'])->toBe('1.0.0')
            ->and($options['plugin-author-uri']['validation'])->toBe('required|url')
            ->and($options['activate-plugin']['default'])->toBe('yes')
            ->and($options['asset']['default'])->toBe('false')
            ->and(array_keys(makePluginProtected($command, 'promptForMissingArgumentsUsing')))->toBe(['name']);
    });

    it('uses the repository given on the command line without asking', function (): void {
        $command = makePluginPrepared(makePluginCommandIn($this->pluginsPath, $this->scaffolder), 'acme', ['--repository' => 'acme/starter']);

        expect(makePluginProtected($command, 'promptForRepository'))->toBe('acme/starter');
    });
});

describe('asset files of the template', function (): void {
    it('leaves the asset tooling out unless assets are included', function (string $path, bool $excluded): void {
        $command = makePluginPrepared(makePluginCommandIn($this->pluginsPath, $this->scaffolder), 'acme');
        $item = new readonly class($path)
        {
            public function __construct(private string $path) {}

            public function getRelativePathname(): string
            {
                return $this->path;
            }
        };

        expect(makePluginProtected($command, 'shouldExcludeAssetFile', $item))->toBe($excluded);
    })->with([
        'vite config' => ['vite.config.js', true],
        'tailwind config' => ['tailwind.config.js', true],
        'package.json' => ['package.json', true],
        'nested package.json' => ['sub/package.json', true],
        'asset directory' => ['resources/assets/app.js', true],
        'asset provider stub' => ['app/Providers/AssetServiceProvider.stub', true],
        'main file' => ['acme.php', false],
        'php class' => ['app/Plugin.php', false],
        'file that only ends like package.json' => ['mypackage.json', false],
        'other resources' => ['resources/views/page.blade.php', false],
    ]);

    it('keeps every file when assets are included', function (): void {
        $command = makePluginPrepared(makePluginCommandIn($this->pluginsPath, $this->scaffolder), 'acme', ['--asset' => 'true']);
        $item = new class
        {
            public function getRelativePathname(): string
            {
                return 'vite.config.js';
            }
        };

        expect(makePluginProtected($command, 'shouldExcludeAssetFile', $item))->toBeFalse();
    });
});

describe('replacements', function (): void {
    it('derives the function names, namespace, slug and basename from the plugin name', function (): void {
        $command = makePluginPrepared(makePluginCommandIn($this->pluginsPath, $this->scaffolder), 'Acme-Shop 2', [
            '--plugin-author' => 'Acme',
            '--plugin-version' => '9.9.9',
        ]);

        $replacements = makePluginProtected($command, 'getReplacements');

        expect($replacements)->toMatchArray([
            '%plugin_name%' => 'Acme-Shop 2',
            '%plugin_function_name%' => 'acme_shop_2',
            '%PLUGIN_FUNCTION_NAME%' => 'ACME_SHOP_2',
            '%PLUGIN_NAME%' => 'ACME_SHOP_2',
            '%plugin_author%' => 'Acme',
            '%plugin_version%' => '9.9.9',
            '%plugin_namespace%' => 'AcmeShop2',
            '%plugin_slug%' => 'acme-shop-2',
            '%plugin_basename%' => 'Acme-Shop 2/Acme-Shop 2.php',
        ]);
    });

    it('makes a valid PHP function name of any plugin name', function (string $name, string $expected): void {
        $command = makePluginCommandIn($this->pluginsPath, $this->scaffolder);

        expect(makePluginProtected($command, 'sanitizeForPhpFunction', $name))->toBe($expected);
    })->with([
        ['acme-shop', 'acme_shop'],
        ['Acme  Shop', 'acme_shop'],
        ['--acme--', 'acme'],
        ['2fast', '_2fast'],
        ['already_ok', 'already_ok'],
    ]);
});
