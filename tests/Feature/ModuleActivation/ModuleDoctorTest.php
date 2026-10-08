<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Modules\Infrastructure\Activation\ConnectorActivator;
use Pollora\Modules\Infrastructure\Checks\ModuleActivationCheck;
use Pollora\Modules\UI\Console\ModuleFrontendCommand;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().'/pollora-module-doctor-'.uniqid();
    File::ensureDirectoryExists($this->directory.'/Modules/Crm');
    $this->statuses = $this->directory.'/modules_statuses.json';
    file_put_contents($this->statuses, json_encode(['Crm' => true]));

    config([
        'modules.connector' => 'json',
        'modules.connectors' => [
            'json' => ['path' => $this->statuses],
            'database' => ['option' => 'pollora_modules', 'fallback' => 'json', 'connection' => 'missing_wordpress'],
        ],
        'modules.admin.toggle' => true,
        'database.connections.missing_wordpress' => ['driver' => 'sqlite', 'database' => ':memory:'],
    ]);

    $modulePath = $this->directory.'/Modules/Crm';
    $this->app->instance('modules', new readonly class($modulePath)
    {
        public function __construct(private string $path) {}

        public function all(): array
        {
            return ['Crm' => $this->find('Crm')];
        }

        public function find(string $name): ?object
        {
            $path = $this->path;

            return $name === 'Crm' ? new readonly class($path)
            {
                public function __construct(private string $path) {}

                public function getName(): string
                {
                    return 'Crm';
                }

                public function getPath(): string
                {
                    return $this->path;
                }
            } : null;
        }
    });
    $this->app->instance(ActivatorInterface::class, new ConnectorActivator($this->app));
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
    putenv('MODULES_CONNECTOR');
});

describe('Module activation check', function (): void {
    it('passes when the states match the modules and can be written', function (): void {
        $result = $this->app->make(ModuleActivationCheck::class)->run(RunContext::Console);

        expect($result->status->value)->toBe('ok')
            ->and($result->summary)->toContain('modules_statuses.json');
    });

    it('names a module the states list but the disk no longer has', function (): void {
        file_put_contents($this->statuses, json_encode(['Crm' => true, 'Billing' => false]));

        $result = $this->app->make(ModuleActivationCheck::class)->run(RunContext::Console);

        expect($result->status->value)->toBe('warning')
            ->and($result->details[0])->toContain('no longer on disk: Billing');
    });

    it('says when the database connector reads its fallback', function (): void {
        config(['modules.connector' => 'database']);
        $this->app->instance(ActivatorInterface::class, new ConnectorActivator($this->app));

        $result = $this->app->make(ModuleActivationCheck::class)->run(RunContext::Console);

        expect($result->details[0])->toContain('The pollora_modules option could not be read: module states come from the fallback (JSON file)');
    });

    it('warns about a configuration cache written before the last switch', function (): void {
        $cachedConfig = $this->app->getCachedConfigPath();
        File::ensureDirectoryExists(dirname($cachedConfig));
        $existed = is_file($cachedConfig);
        $previous = $existed ? file_get_contents($cachedConfig) : null;
        file_put_contents($cachedConfig, '<?php return [];');
        touch($cachedConfig, time() - 60);

        try {
            $result = $this->app->make(ModuleActivationCheck::class)->run(RunContext::Console);
        } finally {
            $existed ? file_put_contents($cachedConfig, $previous) : unlink($cachedConfig);
        }

        expect(implode("\n", $result->details))->toContain('The configuration cache was written before the last module switch');
    });

    it('says MODULES_* settings are ignored without the connector activator', function (): void {
        $this->app->instance(ActivatorInterface::class, Mockery::mock(ActivatorInterface::class));
        config(['modules.activators.file.statuses-file' => $this->statuses]);
        putenv('MODULES_CONNECTOR=database');

        $result = $this->app->make(ModuleActivationCheck::class)->run(RunContext::Console);

        expect(implode("\n", $result->details))->toContain('MODULES_CONNECTOR set, but config/modules.php is not published')
            ->and($result->fix)->toContain('vendor:publish --tag=pollora-modules');
    });
});

describe('pollora:module:frontend', function (): void {
    beforeEach(function (): void {
        $this->app->make(Kernel::class)->registerCommand($this->app->make(ModuleFrontendCommand::class));
    });

    it("replaces the stock Vite build with the template's, keeping a backup", function (): void {
        $module = $this->directory.'/Modules/Crm';
        file_put_contents($module.'/vite.config.js', "buildDirectory: 'build-crm'");

        $this->artisan('pollora:module:frontend', ['module' => 'Crm'])->assertSuccessful();

        expect((string) file_get_contents($module.'/vite.config.js'))->toContain("pollora({ type: 'module', name: 'crm' })")
            ->and((string) file_get_contents($module.'/vite.config.js.bak'))->toBe("buildDirectory: 'build-crm'")
            ->and(json_decode((string) file_get_contents($module.'/package.json'), true)['devDependencies'])->toHaveKey('@pollora/vite-config')
            ->and($module.'/resources/assets/app.css')->toBeFile()
            ->and($module.'/package.json.bak')->not->toBeFile();
    });

    it('refuses an unknown module', function (): void {
        $this->artisan('pollora:module:frontend', ['module' => 'Billing'])->assertFailed();
    });
});
