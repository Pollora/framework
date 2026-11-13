<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\View\Engine;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\View\Compilers\CompilerInterface;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Factory;
use Illuminate\View\View;
use Pollora\View\ViewServiceProvider;

beforeEach(function (): void {
    $this->previousContainer = Container::getInstance();
    $this->dir = sys_get_temp_dir().'/pollora-macros-'.bin2hex(random_bytes(6));
    mkdir($this->dir);
    $app = new Application($this->dir);
    $app->instance('config', new Repository(['view' => ['compiled' => $this->dir]]));
    Container::setInstance($app);
    (new ViewServiceProvider($app))->register();
});

afterEach(function (): void {
    Container::setInstance($this->previousContainer);
    (new Filesystem)->deleteDirectory($this->dir);
});

function macroView(string $name, string $path, ?Engine $engine = null): View
{
    return new View(Mockery::mock(Factory::class), $engine ?? Mockery::mock(Engine::class), $name, $path);
}

describe('View::getCompiled() macro', function (): void {
    it('gives the compiled path when the engine compiles Blade', function (): void {
        $compiler = Mockery::mock(CompilerInterface::class);
        $compiler->shouldReceive('getCompiledPath')->with('/v/page.blade.php')->andReturn('/c/abc.php');
        $engine = Mockery::mock(CompilerEngine::class);
        $engine->shouldReceive('getCompiler')->andReturn($compiler);

        expect(macroView('page', '/v/page.blade.php', $engine)->getCompiled())->toBe('/c/abc.php');
    });

    it('gives the view file itself when the engine does not compile', function (): void {
        expect(macroView('page', '/v/page.php')->getCompiled())->toBe('/v/page.php');
    });
});

describe('View::makeLoader() macro', function (): void {
    it('writes a stable loader that renders the view by name', function (): void {
        $path = '/v/it\'s.blade.php';
        $loader = macroView("pages.it's", $path)->makeLoader();

        expect($loader)->toBe($this->dir.'/'.md5($path).'-loader.php')
            ->and(file_get_contents($loader))->toBe("<?= \\view('pages.it\\'s', \$data ?? get_defined_vars())->render(); ?>\n<?php /**PATH {$path} ENDPATH**/ ?>");
    });

    it('does not rewrite a loader that already exists', function (): void {
        $path = '/v/page.blade.php';
        $loader = $this->dir.'/'.md5($path).'-loader.php';
        file_put_contents($loader, 'existing');

        expect(macroView('page', $path)->makeLoader())->toBe($loader)
            ->and(file_get_contents($loader))->toBe('existing');
    });
});
