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
            $compiled_path = resolve('config')['view.compiled'];

            // Use file modification time in the hash so loaders invalidate when templates change
            $viewHash = md5($path);
            $timeHash = substr(md5((string) (file_exists($path) ? filemtime($path) : 0)), 0, 8);
            $loader = sprintf('%s/%s-%s-loader.php', $compiled_path, $viewHash, $timeHash);

            $content = sprintf("<?= \\view('%s', \$data ?? get_defined_vars())->render(); ?>", addslashes($view))
                ."\n<?php /**PATH {$path} ENDPATH**/ ?>";

            if (! file_exists($loader)) {
                // Clean up stale loaders for this view before creating the new one
                array_map('unlink', glob(sprintf('%s/%s-*-loader.php', $compiled_path, $viewHash)) ?: []);
                file_put_contents($loader, $content);
            }

            return $loader;
        });
    }
}
