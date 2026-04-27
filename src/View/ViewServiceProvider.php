<?php

declare(strict_types=1);

namespace Pollora\View;

use Illuminate\Contracts\View\Engine;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\View;
use Illuminate\View\ViewServiceProvider as ViewServiceProviderBase;

class ViewServiceProvider extends ViewServiceProviderBase
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerMacros();
    }

    /**
     * Register View Macros
     */
    public function registerMacros(): void
    {
        /**
         * Creates a loader for the view to be called later
         *
         * @return string
         */
        View::macro('makeLoader', function (): string {
            $view = $this->getName();
            $path = $this->getPath();

            /** @var Engine $engine */
            $engine = $this->getEngine();
            $compiled = ($engine instanceof CompilerEngine)
                ? $engine->getCompiler()->getCompiledPath($path)
                : $path;

            $id = md5($compiled);
            $compiled_path = resolve('config')['view.compiled'];

            $content = sprintf("<?= \\view('%s', \$data ?? get_defined_vars())->render(); ?>", $view)
                ."\n<?php /**PATH {$path} ENDPATH**/ ?>";

            if (! file_exists($loader = sprintf('%s/%s-loader.php', $compiled_path, $id))) {
                file_put_contents($loader, $content);
            }

            return $loader;
        });
    }
}
