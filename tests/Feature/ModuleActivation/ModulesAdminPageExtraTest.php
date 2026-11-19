<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Pollora\Modules\Application\Services\ModuleStates;
use Pollora\Modules\Application\Services\ModuleVersions;
use Pollora\Modules\Infrastructure\Activation\ConnectorActivator;
use Pollora\Modules\Infrastructure\Activation\JsonStateConnector;
use Pollora\Modules\UI\Http\ModulesAdminPage;
use Pollora\VersionCheck\Domain\Contracts\VersionSourceResolver;
use Pollora\VersionCheck\Infrastructure\Sources\HttpGet;
use Pollora\VersionCheck\Infrastructure\Sources\VersionSources;

/**
 * A module as nwidart/laravel-modules hands it out, switched through the activator.
 */
function extraFakeModule(string $name, ActivatorInterface $activator): object
{
    return new readonly class($name, $activator)
    {
        public function __construct(private string $name, private ActivatorInterface $activator) {}

        public function getName(): string
        {
            return $this->name;
        }

        public function getDescription(): string
        {
            return $this->name.' module';
        }

        public function getPath(): string
        {
            return base_path('Modules/'.$this->name);
        }

        public function isEnabled(): bool
        {
            return $this->activator->hasStatus($this->name, true);
        }

        public function enable(): void
        {
            $this->activator->setActiveByName($this->name, true);
        }

        public function disable(): void
        {
            $this->activator->setActiveByName($this->name, false);
        }
    };
}

beforeEach(function (): void {
    Functions\stubEscapeFunctions();
    Functions\stubs([
        'admin_url' => fn (string $path = ''): string => 'https://example.test/wp-admin/'.$path,
        'wp_json_encode' => fn (mixed $value): string => (string) json_encode($value),
        'wp_nonce_field' => fn (): string => '',
        'check_admin_referer' => true,
        'sanitize_text_field' => trim(...),
        'sanitize_key' => strtolower(...),
        'wp_unslash' => fn (mixed $value): mixed => $value,
        'get_current_user_id' => 1,
        'get_transient' => false,
    ]);
    Functions\when('current_user_can')->justReturn(true);

    $this->directory = sys_get_temp_dir().'/pollora-modules-admin-'.uniqid();
    File::ensureDirectoryExists($this->directory);
    $this->statuses = $this->directory.'/modules_statuses.json';
    file_put_contents($this->statuses, json_encode(['Crm' => true, 'Blog' => false, 'Shop' => true]));

    config([
        'modules.connector' => 'json',
        'modules.connectors.json.path' => $this->statuses,
        'modules.locked' => ['enabled' => ['Shop'], 'disabled' => []],
        'modules.admin' => ['toggle' => true, 'capability' => 'activate_plugins'],
    ]);

    $activator = new ConnectorActivator($this->app);
    $this->app->instance(ActivatorInterface::class, $activator);
    $modules = ['Crm' => extraFakeModule('Crm', $activator), 'Blog' => extraFakeModule('Blog', $activator), 'Shop' => extraFakeModule('Shop', $activator)];

    $this->app->instance('modules', new readonly class($modules)
    {
        public function __construct(private array $modules) {}

        public function all(): array
        {
            return $this->modules;
        }

        public function find(string $name): ?object
        {
            return $this->modules[$name] ?? null;
        }
    });

    $this->app->instance(VersionSources::class, $sources = new VersionSources(new HttpGet, $this->directory.'/composer.json'));
    $this->app->instance(VersionSourceResolver::class, $sources);

    $this->page = $this->app->make(ModulesAdminPage::class);
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

describe('ModulesAdminPage menu and plugin views', function (): void {
    it('adds the Modules page under Plugins with the capability the states ask for', function (): void {
        $added = [];
        Functions\when('add_submenu_page')->alias(function (...$args) use (&$added): string {
            $added[] = $args;

            return 'hook';
        });

        $this->page->addMenuPage();

        expect($added)->toHaveCount(1)
            ->and($added[0][0])->toBe('plugins.php')
            ->and($added[0][1])->toBe('Modules')
            ->and($added[0][3])->toBe('activate_plugins')
            ->and($added[0][4])->toBe(ModulesAdminPage::SLUG)
            ->and($added[0][5])->toBeCallable();
    });

    it('adds no page, and no plugin view, when nwidart is not installed', function (): void {
        $added = 0;
        Functions\when('add_submenu_page')->alias(function () use (&$added): string {
            $added++;

            return 'hook';
        });
        $this->app->forgetInstance('modules');
        $this->app->offsetUnset('modules');

        $page = new ModulesAdminPage($this->app->make(ModuleStates::class), $this->app->make(ModuleVersions::class));

        $page->addMenuPage();

        expect($added)->toBe(0)
            ->and($page->addView(['all' => 'All']))->toBe(['all' => 'All']);
    });

    it('adds no plugin view for a user who cannot manage modules', function (): void {
        Functions\when('current_user_can')->justReturn(false);

        expect($this->page->addView(['all' => 'All']))->toBe(['all' => 'All']);
    });

    it('does nothing on a request that is not a POST', function (): void {
        $redirected = false;
        Functions\when('wp_safe_redirect')->alias(function () use (&$redirected): bool {
            $redirected = true;

            return true;
        });

        $this->page->handleRequest();

        expect($redirected)->toBeFalse()
            ->and((new JsonStateConnector($this->statuses))->all()['Blog'])->toBeFalse();
    });

    it('links to its own screen in the admin', function (): void {
        expect($this->page->url())->toBe('https://example.test/wp-admin/plugins.php?page=pollora-modules');
    });
});

describe('ModulesAdminPage switching outcomes', function (): void {
    it('asks for a module and an action when the request names none', function (array $request): void {
        $notice = $this->page->switchFromRequest($request);

        expect($notice['type'])->toBe('warning')
            ->and($notice['messages'])->toBe(['Select a module and an action.']);
    })->with([
        'empty' => [[]],
        'action without module' => [['bulk_action' => 'enable']],
        'module without action' => [['modules' => ['Blog']]],
        'unknown action' => [['bulk_action' => 'delete', 'modules' => ['Blog']]],
    ]);

    it('reports an error when every switch is refused', function (): void {
        $notice = $this->page->switchFromRequest(['toggle' => 'disable:Shop']);

        expect($notice['type'])->toBe('error')
            ->and($notice['messages'])->toHaveCount(1)
            ->and($notice['messages'][0])->toContain('locked enabled');
    });

    it('enables several modules at once and lists them', function (): void {
        $notice = $this->page->switchFromRequest(['bulk_action' => 'enable', 'modules' => ['Blog', 'Crm']]);

        expect($notice['type'])->toBe('success')
            ->and($notice['messages'])->toBe(['Blog, Crm enabled. The change applies from the next request.'])
            ->and((new JsonStateConnector($this->statuses))->all()['Blog'])->toBeTrue();
    });
});

describe('ModulesAdminPage rendering', function (): void {
    function renderedPage(ModulesAdminPage $page): string
    {
        ob_start();
        $page->render();

        return (string) ob_get_clean();
    }

    it('says no module was found when there is none', function (): void {
        $this->app->instance('modules', new class
        {
            public function all(): array
            {
                return [];
            }

            public function find(string $name): ?object
            {
                return null;
            }
        });

        $html = renderedPage($this->app->make(ModulesAdminPage::class));

        expect($html)->toContain('No modules found.')->toContain('(0)');
    });

    it('explains, and offers no switch, when the toggle is turned off', function (): void {
        config(['modules.admin.toggle' => false]);

        $html = renderedPage($this->app->make(ModulesAdminPage::class));

        expect($html)->toContain('MODULES_ADMIN_TOGGLE=false')
            ->not->toContain('bulk_action')
            ->not->toContain('name="toggle"');
    });

    it('shows a pending notice once and deletes it', function (): void {
        $deleted = [];
        Functions\when('get_transient')->justReturn(['type' => 'success', 'messages' => ['Crm disabled.', '<b>x</b>']]);
        Functions\when('delete_transient')->alias(function (string $key) use (&$deleted): bool {
            $deleted[] = $key;

            return true;
        });

        $html = renderedPage($this->page);

        expect($html)->toContain('notice-success is-dismissible')
            ->toContain('<p>Crm disabled.</p>')
            ->and($deleted)->toBe(['pollora_modules_notice_1']);
    });

    it('marks a development build and gives no update command for it', function (): void {
        $versions = Mockery::mock(ModuleVersions::class);
        $versions->shouldReceive('all')->andReturn(['Crm' => [
            'package' => 'acme/crm', 'version' => 'dev-main', 'latest' => '2.0.0', 'development' => true, 'update' => true, 'release_url' => null,
        ]]);
        $this->app->instance(ModuleVersions::class, $versions);

        $html = renderedPage($this->app->make(ModulesAdminPage::class));

        expect($html)->toContain('dev-main <span class="description">(development build)</span>')
            ->not->toContain('composer update');
    });

    it('shows the bare version of an up to date module, and an update without a release link as plain text', function (): void {
        $versions = Mockery::mock(ModuleVersions::class);
        $versions->shouldReceive('all')->andReturn([
            'Crm' => ['package' => 'acme/crm', 'version' => '1.0.0', 'latest' => '1.0.0', 'development' => false, 'update' => false, 'release_url' => null],
            'Blog' => ['package' => 'acme/blog', 'version' => '1.0.0', 'latest' => '1.1.0', 'development' => false, 'update' => true, 'release_url' => null],
        ]);
        $this->app->instance(ModuleVersions::class, $versions);

        $html = renderedPage($this->app->make(ModulesAdminPage::class));

        expect($html)->toContain('<strong>1.1.0 available</strong>')
            ->not->toContain('<a href="" target')
            ->and(substr_count($html, 'composer update'))->toBe(1)
            ->and($html)->toContain('<code>composer update acme/blog</code>');
    });

    it('shows each module path relative to the project', function (): void {
        $html = renderedPage($this->page);

        expect($html)->toContain('<code>Modules/Crm</code>');
    });
});
