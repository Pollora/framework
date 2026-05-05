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
            $id = md5($this->getCompiled());
            $compiled_path = resolve('config')['view.compiled'];

            $content = "<?= \\view('{$view}', \$data ?? array_filter(get_defined_vars(), fn(\$v, \$k) => !\is_string(\$v) || !\in_array(\$k, ['product', 'post'], true), ARRAY_FILTER_USE_BOTH))->render(); ?>"
                ."\n<?php /**PATH {$path} ENDPATH**/ ?>";

            if (! file_exists($loader = sprintf('%s/%s-loader.php', $compiled_path, $id))) {
                file_put_contents($loader, $content);
            }

            return $loader;
        });
    }
}
