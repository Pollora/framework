<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\View\ViewFinderInterface;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Collection\Domain\Contracts\CollectionInterface;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Modules\Infrastructure\Services\ModuleAssetManager;
use Pollora\Theme\Application\Services\ThemeManager;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;
use Pollora\Theme\Domain\Exceptions\ThemeException;
use Pollora\Theme\Domain\Models\ThemeMetadata;
use Pollora\Theme\Domain\Models\ThemeModule;

function managerThemesRoot(): string
{
    $root = sys_get_temp_dir().'/pollora-manager-'.bin2hex(random_bytes(6));
    mkdir($root);

    return $root;
}

function managerRemove(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $dir.'/'.$entry;
        is_dir($path) ? managerRemove($path) : unlink($path);
    }

    rmdir($dir);
}

class ManagerTestContainer extends Container
{
    public function basePath(string $path = ''): string
    {
        return '/base'.($path !== '' ? '/'.$path : '');
    }
}

beforeEach(function (): void {
    $this->previousContainer = Container::getInstance();
});

afterEach(function (): void {
    Container::setInstance($this->previousContainer);
});

function managerMake(string $root, ?Container $app = null, ?Loader $loader = null, ?ModuleRepositoryInterface $repo = null, ?ThemeRegistrarInterface $registrar = null, bool $console = false): ThemeManager
{
    $app ??= new ManagerTestContainer;
    Container::setInstance($app);
    $app->instance('config', new Repository(['theme' => ['path' => $root.'/']]));
    $console = tap(Mockery::mock(ConsoleDetectionService::class), fn ($m) => $m->shouldReceive('isConsole')->andReturn($console));

    return new ThemeManager($app, Mockery::mock(ViewFinderInterface::class), $loader, $repo, $registrar, $console);
}

describe('ThemeManager loading', function (): void {
    it('walks up the parent chain and registers every theme view path', function (): void {
        $root = managerThemesRoot();
        mkdir($root.'/child');
        mkdir($root.'/parent');
        file_put_contents($root.'/child/theme.json', json_encode(['parent' => 'parent']));
        file_put_contents($root.'/parent/theme.json', json_encode([]));

        $finder = Mockery::mock(ViewFinderInterface::class);
        $finder->shouldReceive('addLocation')->once()->with($root.'/child/resources/views');
        $finder->shouldReceive('addLocation')->once()->with($root.'/parent/resources/views');
        $view = new class($finder)
        {
            public function __construct(private $finder) {}

            public function getFinder()
            {
                return $this->finder;
            }
        };
        $app = new ManagerTestContainer;
        $app->instance('view', $view);

        $loader = Mockery::mock(Loader::class);
        $loader->shouldReceive('addNamespace')->once()->with('child', $root.'/child/lang');

        try {
            $manager = managerMake($root, $app, $loader);
            $manager->load('child');
            $result = [$manager->theme()->getName(), array_map(fn (ThemeMetadata $t): string => $t->getName(), $manager->getParentThemes())];
        } finally {
            managerRemove($root);
        }

        expect($result)->toBe(['child', ['parent']]);
    });

    it('registers view paths through the module asset manager when it is bound', function (): void {
        $root = managerThemesRoot();
        mkdir($root.'/solo');
        file_put_contents($root.'/solo/theme.json', '{}');
        $assets = Mockery::mock(ModuleAssetManager::class);
        $assets->shouldReceive('registerModuleViewPaths')->once()->with($root.'/solo', 'theme', 'solo');
        $app = new ManagerTestContainer;
        $app->instance(ModuleAssetManager::class, $assets);

        try {
            managerMake($root, $app)->load('solo');
        } finally {
            managerRemove($root);
        }
    });

    it('rejects a missing theme directory outside the console', function (): void {
        $root = managerThemesRoot();

        try {
            managerMake($root)->load('ghost');
        } finally {
            managerRemove($root);
        }
    })->throws(ThemeException::class, 'Theme directory ghost not found.');

    it('tolerates a missing theme directory in the console', function (): void {
        $root = managerThemesRoot();
        $assets = Mockery::mock(ModuleAssetManager::class);
        $assets->shouldReceive('registerModuleViewPaths')->once();
        $app = new ManagerTestContainer;
        $app->instance(ModuleAssetManager::class, $assets);

        try {
            $manager = managerMake($root, $app, null, null, null, true);
            $manager->load('ghost');
            $name = $manager->theme()->getName();
        } finally {
            managerRemove($root);
        }

        expect($name)->toBe('ghost');
    });
});

describe('ThemeManager paths and listing', function (): void {
    it('lists only the directories that carry a theme.json', function (): void {
        $root = managerThemesRoot();
        mkdir($root.'/with');
        mkdir($root.'/without');
        file_put_contents($root.'/with/theme.json', '{}');

        try {
            $themes = array_values(managerMake($root)->getAvailableThemes());
        } finally {
            managerRemove($root);
        }

        expect($themes)->toBe(['with']);
    });

    it('lists nothing when the themes directory does not exist', function (): void {
        expect(managerMake('/does/not/exist-'.bin2hex(random_bytes(4)))->getAvailableThemes())->toBe([]);
    });

    it('builds a path inside the active theme, trimming slashes', function (): void {
        Functions\when('get_stylesheet')->justReturn('demo');

        expect(managerMake('/themes')->path('/assets/app.css'))->toBe('/themes/demo/assets/app.css')
            ->and(managerMake('/themes')->active())->toBe('demo');
    });
});

describe('ThemeManager module delegation', function (): void {
    it('returns neutral values when no repository or registrar is configured', function (): void {
        $manager = managerMake('/themes');

        expect($manager->getAllThemes())->toBeNull()
            ->and($manager->getAllThemesAsArray())->toBe([])
            ->and($manager->getEnabledThemes())->toBe([])
            ->and($manager->getDisabledThemes())->toBe([])
            ->and($manager->findTheme('x'))->toBeNull()
            ->and($manager->getActiveTheme())->toBeNull()
            ->and($manager->hasTheme('x'))->toBeFalse()
            ->and($manager->isThemeActive('x'))->toBeFalse()
            ->and($manager->scanThemes())->toBe([])
            ->and($manager->getThemeCount())->toBe(0);

        $manager->registerThemes();
        expect($manager->validateTheme('x'))->toBe(['valid' => false, 'errors' => ['Theme not found']]);
    });

    it('delegates to the repository and the registrar', function (): void {
        $theme = new ThemeModule('demo', '/p');
        $collection = Mockery::mock(CollectionInterface::class);
        $repo = Mockery::mock(ModuleRepositoryInterface::class);
        $repo->shouldReceive('toCollection')->andReturn($collection);
        $repo->shouldReceive('all')->andReturn(['demo' => $theme]);
        $repo->shouldReceive('allEnabled')->andReturn(['demo' => $theme]);
        $repo->shouldReceive('allDisabled')->andReturn([]);
        $repo->shouldReceive('find')->with('demo')->andReturn($theme);
        $repo->shouldReceive('find')->with('nope')->andReturnNull();
        $repo->shouldReceive('has')->with('demo')->andReturnTrue();
        $repo->shouldReceive('scan')->andReturn(['demo' => $theme]);
        $repo->shouldReceive('register')->once();
        $repo->shouldReceive('count')->andReturn(7);
        $registrar = Mockery::mock(ThemeRegistrarInterface::class);
        $registrar->shouldReceive('getActiveTheme')->andReturn($theme);
        $registrar->shouldReceive('isThemeActive')->with('demo')->andReturnTrue();
        $manager = managerMake('/themes', null, null, $repo, $registrar);

        expect($manager->getAllThemes())->toBe($collection)
            ->and($manager->getAllThemesAsArray())->toBe(['demo' => $theme])
            ->and($manager->getEnabledThemes())->toBe(['demo' => $theme])
            ->and($manager->getDisabledThemes())->toBe([])
            ->and($manager->findTheme('demo'))->toBe($theme)
            ->and($manager->findTheme('nope'))->toBeNull()
            ->and($manager->getActiveTheme())->toBe($theme)
            ->and($manager->hasTheme('demo'))->toBeTrue()
            ->and($manager->isThemeActive('demo'))->toBeTrue()
            ->and($manager->scanThemes())->toBe(['demo' => $theme])
            ->and($manager->getThemeCount())->toBe(7);

        $manager->registerThemes();
    });

    it('reports theme information, including whether it is active', function (): void {
        $theme = (new ThemeModule('demo', '/nowhere'))->setHeaders(['Version' => '2.0', 'Author' => 'Ann', 'Template' => 'base'])->setEnabled(true);
        $repo = Mockery::mock(ModuleRepositoryInterface::class);
        $repo->shouldReceive('find')->with('demo')->andReturn($theme);
        $registrar = Mockery::mock(ThemeRegistrarInterface::class);
        $registrar->shouldReceive('isThemeActive')->with('demo')->andReturnTrue();

        expect(managerMake('/themes', null, null, $repo, $registrar)->getThemeInfo('demo'))->toMatchArray([
            'name' => 'demo',
            'version' => '2.0',
            'author' => 'Ann',
            'path' => '/nowhere',
            'enabled' => true,
            'active' => true,
            'stylesheet' => 'demo',
            'template' => 'base',
            'is_child_theme' => true,
            'parent_theme' => 'base',
            'screenshot' => null,
        ]);
    });

    it('throws when asked for the information of an unknown theme', function (): void {
        $repo = Mockery::mock(ModuleRepositoryInterface::class);
        $repo->shouldReceive('find')->andReturnNull();

        managerMake('/themes', null, null, $repo)->getThemeInfo('ghost');
    })->throws(ThemeException::class, "Theme 'ghost' not found.");

    it('validates the required files of a theme', function (): void {
        $dir = managerThemesRoot();
        $theme = new ThemeModule('demo', $dir);
        $repo = Mockery::mock(ModuleRepositoryInterface::class);
        $repo->shouldReceive('find')->with('demo')->andReturn($theme);
        $manager = managerMake('/themes', null, null, $repo);

        try {
            $empty = $manager->validateTheme('demo');
            file_put_contents($dir.'/style.css', '');
            $partial = $manager->validateTheme('demo');
            file_put_contents($dir.'/index.php', '');
            $full = $manager->validateTheme('demo');
        } finally {
            managerRemove($dir);
        }

        expect($empty)->toBe(['valid' => false, 'errors' => ['Missing required file: style.css', 'Missing required file: index.php']])
            ->and($partial)->toBe(['valid' => false, 'errors' => ['Missing required file: index.php']])
            ->and($full)->toBe(['valid' => true, 'errors' => []]);
    });

    it('reports a missing theme directory when validating', function (): void {
        $theme = new ThemeModule('demo', '/does/not/exist-'.bin2hex(random_bytes(4)));
        $repo = Mockery::mock(ModuleRepositoryInterface::class);
        $repo->shouldReceive('find')->andReturn($theme);

        expect(managerMake('/themes', null, null, $repo)->validateTheme('demo')['errors'])->toContain('Theme directory does not exist');
    });

    it('resets the repository and discovery caches', function (): void {
        $repo = Mockery::mock(ModuleRepositoryInterface::class);
        $repo->shouldReceive('resetCache')->once();
        $discovery = new class
        {
            public int $resets = 0;

            public function resetCache(): void
            {
                $this->resets++;
            }
        };
        $manager = managerMake('/themes', null, null, $repo);
        $manager->discovery = $discovery;

        $manager->resetCache();

        expect($discovery->resets)->toBe(1);
    });
});
