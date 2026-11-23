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
         * Get the compiled path of the view
         *
         * @return string
         */
        View::macro('getCompiled', function () {
            /** @var string $file path to file */
            $file = $this->getPath();

            /** @var Engine $engine */
            $engine = $this->getEngine();

            return ($engine instanceof CompilerEngine)
                ? $engine->getCompiler()->getCompiledPath($file)
                : $file;
        });

        /**
         * Creates a loader for the view to be called later
         *
         * @return string
         */
        View::macro('makeLoader', function (): string {
            $view = $this->getName();
            $path = $this->getPath();
            $id = md5($path);
            $compiled_path = resolve('config')['view.compiled'];

            // The loader is a stable passthrough file that delegates to view()->render().
            // Blade recompilation is handled by Laravel's CompilerEngine::isExpired(),
            // so the loader itself never needs to be invalidated.
            $content = sprintf("<?= \\view('%s', \$data ?? get_defined_vars())->render(); ?>", addslashes($view))
                ."\n<?php /**PATH {$path} ENDPATH**/ ?>";

            // Written to a temporary file then renamed: a concurrent request including the loader
            // never reads half of it, and an empty file left by an interrupted write is replaced
            // (it is a blank page for the view, for ever, otherwise).
            $loader = sprintf('%s/%s-loader.php', $compiled_path, $id);

            if (! is_file($loader) || filesize($loader) === 0) {
                $temporary = $loader.'.'.bin2hex(random_bytes(6)).'.tmp';

                if (file_put_contents($temporary, $content) !== false && ! @rename($temporary, $loader)) {
                    @unlink($temporary);
                }
            }

            return $loader;
        });
    }
}
