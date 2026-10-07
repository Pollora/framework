<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\WordPress\QueryTrait;

/**
 * WordPress is loaded from a provider's boot, and the theme's providers boot
 * after it: template_redirect waits for every provider to have booted, so a
 * page a plugin renders from it gets the theme's composers and shared data (#419).
 */
beforeEach(function (): void {
    $this->originalScript = $_SERVER['SCRIPT_FILENAME'] ?? null;
    $this->originalFacadeApp = Facade::getFacadeApplication();

    // No script to compare against: the request is Laravel's.
    unset($_SERVER['SCRIPT_FILENAME']);

    $this->bootedCallbacks = [];
    $callbacks = &$this->bootedCallbacks;
    $app = new Container;
    $app->instance('app', new class($callbacks)
    {
        public function __construct(private array &$callbacks) {}

        public function booted(callable $callback): void
        {
            $this->callbacks[] = $callback;
        }
    });
    Facade::clearResolvedInstance('app');
    Facade::setFacadeApplication($app);

    $this->fired = [];
    $fired = &$this->fired;
    $action = Mockery::mock(Action::class);
    $this->action = $action;
    $this->action->allows('do')->andReturnUsing(function (string $hook) use (&$fired, $action): Action {
        $fired[] = $hook;

        return $action;
    });

    Functions\when('wp')->alias(function () use (&$fired): void {
        $fired[] = 'wp';
    });
    Functions\stubs(['wp_using_themes' => true, 'is_robots' => false, 'is_favicon' => false, 'is_feed' => false, 'is_trackback' => false]);
});

afterEach(function (): void {
    Facade::clearResolvedInstance('app');
    Facade::setFacadeApplication($this->originalFacadeApp);

    if ($this->originalScript !== null) {
        $_SERVER['SCRIPT_FILENAME'] = $this->originalScript;
    }
});

function wordPressRunner(Action $action): object
{
    return new class($action)
    {
        use QueryTrait;

        public int $guarded = 0;

        public function __construct(protected Action $action) {}

        public function run(): void
        {
            $this->runWp();
        }

        private function withWordPressErrorHandling(callable $callback): void
        {
            $this->guarded++;
            $callback();
        }
    };
}

it('runs the query at once and template_redirect once every provider has booted', function (): void {
    $runner = wordPressRunner($this->action);

    $runner->run();

    expect($this->fired)->toBe(['wp'])
        ->and($this->bootedCallbacks)->toHaveCount(1);

    ($this->bootedCallbacks[0])();

    expect($this->fired)->toBe(['wp', 'template_redirect', 'pollora_loaded'])
        ->and($runner->guarded)->toBe(1);
});

it('fires only pollora_loaded when WordPress runs one of its own entry points', function (): void {
    $dir = sys_get_temp_dir().'/pollora-entry-'.bin2hex(random_bytes(6));
    mkdir($dir.'/public/cms', 0777, true);
    touch($dir.'/public/index.php');
    touch($dir.'/public/cms/wp-login.php');
    $_SERVER['SCRIPT_FILENAME'] = $dir.'/public/cms/wp-login.php';

    // public_path() asks the application for the front controller, absent here.
    $original = Container::getInstance();
    $laravel = new class extends Container
    {
        public string $base = '';

        public function publicPath(string $path = ''): string
        {
            return $this->base.'/'.$path;
        }
    };
    $laravel->base = $dir.'/public';
    Container::setInstance($laravel);

    try {
        wordPressRunner($this->action)->run();
    } finally {
        Container::setInstance($original);
        exec('rm -rf '.escapeshellarg($dir));
    }

    expect($this->fired)->toBe(['pollora_loaded'])
        ->and($this->bootedCallbacks)->toBe([]);
});
