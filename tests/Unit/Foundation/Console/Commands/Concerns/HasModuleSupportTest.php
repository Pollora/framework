<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Pollora\Foundation\Console\Commands\Concerns\HasModuleSupport;

describe('HasModuleSupport', function (): void {
    beforeEach(function (): void {
        // Ensure base_path() works
        $this->originalContainer = Container::getInstance();
        Container::setInstance(new FoundationTestApp);
        $this->command = new class
        {
            use HasModuleSupport {
                getModuleOptions as public;
                getModuleName as public;
                hasModuleOption as public;
                getModulePath as public;
                getModuleNamespace as public;
                getModuleSourcePath as public;
                getModuleSourceNamespace as public;
                resolveModuleLocation as public;
            }

            private ?string $moduleOption = null;

            public function setModuleOption(?string $value): void
            {
                $this->moduleOption = $value;
            }

            public function option($key = null): ?string
            {
                return $this->moduleOption;
            }
        };
    });

    afterEach(function (): void {
        Container::setInstance($this->originalContainer);
    });

    it('returns module option definition', function (): void {
        $options = $this->command->getModuleOptions();

        expect($options)->toBeArray()
            ->and($options[0][0])->toBe('module');
    });

    it('returns null module name when not set', function (): void {
        expect($this->command->getModuleName())->toBeNull();
    });

    it('returns module name when set', function (): void {
        $this->command->setModuleOption('blog');

        expect($this->command->getModuleName())->toBe('blog');
    });

    it('reports no module option when null', function (): void {
        expect($this->command->hasModuleOption())->toBeFalse();
    });

    it('reports module option when set', function (): void {
        $this->command->setModuleOption('blog');

        expect($this->command->hasModuleOption())->toBeTrue();
    });

    it('generates studly module path', function (): void {
        $this->command->setModuleOption('user-profile');

        expect($this->command->getModulePath())->toBe('/base'.DIRECTORY_SEPARATOR.'Modules/UserProfile');
    });

    it('returns empty path when no module', function (): void {
        expect($this->command->getModulePath())->toBe('');
    });

    it('generates studly module namespace', function (): void {
        $this->command->setModuleOption('user-profile');

        expect($this->command->getModuleNamespace())->toBe('Modules\\UserProfile');
    });

    it('returns empty namespace when no module', function (): void {
        expect($this->command->getModuleNamespace())->toBe('');
    });

    it('appends /app to source path', function (): void {
        $this->command->setModuleOption('blog');

        expect($this->command->getModuleSourcePath())->toBe('/base'.DIRECTORY_SEPARATOR.'Modules/Blog/app');
    });

    it('appends backslash to source namespace', function (): void {
        $this->command->setModuleOption('blog');

        expect($this->command->getModuleSourceNamespace())->toBe('Modules\\Blog\\');
    });

    it('resolves full module location', function (): void {
        $this->command->setModuleOption('blog');

        $location = $this->command->resolveModuleLocation();

        expect($location['type'])->toBe('module');
        expect($location['namespace'])->toBe('Modules\\Blog');
        expect($location['source_namespace'])->toBe('Modules\\Blog\\');
    });

    it('takes the module path nwidart/laravel-modules found', function (): void {
        Container::getInstance()->instance('modules', new class
        {
            public function find(string $name): ?object
            {
                return $name === 'BlocksDemo' ? new class
                {
                    public function getPath(): string
                    {
                        return '/srv/custom-modules/BlocksDemo/';
                    }
                } : null;
            }
        });
        $this->command->setModuleOption('blocks-demo');

        expect($this->command->getModulePath())->toBe('/srv/custom-modules/BlocksDemo');
    });

    it('reads the namespace the module maps onto its source directory', function (): void {
        // A module is free to pick its namespace: pollora-test's BlocksDemo uses Module\BlocksDemo\
        $modulePath = sys_get_temp_dir().'/pollora-module-'.uniqid();
        mkdir($modulePath.'/app', 0755, true);
        file_put_contents($modulePath.'/composer.json', json_encode(['autoload' => ['psr-4' => [
            'Module\\BlocksDemo\\Database\\Seeders\\' => 'database/seeders/',
            'Module\\BlocksDemo\\' => 'app/',
        ]]]));
        Container::getInstance()->instance('modules', new readonly class($modulePath)
        {
            public function __construct(private string $path) {}

            public function find(string $name): object
            {
                $path = $this->path;

                return new readonly class($path)
                {
                    public function __construct(private string $path) {}

                    public function getPath(): string
                    {
                        return $this->path;
                    }
                };
            }
        });
        $this->command->setModuleOption('blocks-demo');

        expect($this->command->getModuleNamespace())->toBe('Module\\BlocksDemo')
            ->and($this->command->getModuleSourceNamespace())->toBe('Module\\BlocksDemo\\');

        unlink($modulePath.'/composer.json');
        rmdir($modulePath.'/app');
        rmdir($modulePath);
    });

    it('returns empty array when no module option', function (): void {
        expect($this->command->resolveModuleLocation())->toBe([]);
    });
});

if (! class_exists('FoundationTestApp')) {
    class FoundationTestApp extends Container
    {
        public function basePath(?string $path = ''): string
        {
            return '/base'.($path ? DIRECTORY_SEPARATOR.$path : '');
        }

        public function getNamespace(): string
        {
            return 'App\\';
        }
    }
}
