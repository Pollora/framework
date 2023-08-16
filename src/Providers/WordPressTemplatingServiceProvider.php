<?php

declare(strict_types=1);

namespace Pollen\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Pollen\Support\Facades\Filter;

/**
 * Provide extra blade directives to aid in WordPress view development.
 */
class WordPressTemplatingServiceProvider extends ServiceProvider
{
    protected $wp_theme;

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->wp_theme = wp_get_theme();
        $this->app->singleton('wp.theme', function () {
            return $this->wp_theme;
        });

        Blade::directive('usercan', function ($expression) {
            return "<?php if( User::current()->can({$expression}) ): ?>";
        });

        Blade::directive('endusercan', function () {
            return '<?php endif; ?>';
        });

        Blade::directive('loop', function () {
            return '<?php if (have_posts()) { while (have_posts()) { the_post(); ?>';
        });

        Blade::directive('endloop', function () {
            return '<?php }} ?>';
        });

        /**
         * Simulate a WordPress get_template_part() behavior using custom views.
         *
         * Examples:
         * "@template('parts.content', get_post_type())"
         * "@template('parts.content', 'page')"
         *
         * In the first example, the view factory will try to include a "dynamic"
         * view with the following path "parts.content-post" or "parts.content-attachment".
         *
         * In the second example, the view factory tries to include the
         * "parts.content-page" view.
         *
         * We test if the dynamic view exists before trying to render it. If none is found,
         * we render the view defined by the first argument. In the 2 examples, the view
         * "parts.content" is rendered and should therefor exists.
         *
         * As a third argument, you can pass custom data array to the included view.
         */
        Blade::directive('template', function ($expression) {
            // Get a list of passed arguments.
            $args = array_map(function ($arg) {
                return trim($arg, '\/\'\" ()');
            }, explode(',', $expression));

            // Set the view path.
            if (isset($args[1])) {
                if (is_callable($args[1])) {
                    $args[1] = call_user_func($args[1]);
                }

                $path = $args[0].'-'.$args[1];
            } else {
                $path = $args[0];
            }

            // Set the view data if defined.
            $data = 3 === count($args) ? array_pop($args) : '[]';

            return "<?php if (\$__env->exists('{$path}')) { echo \$__env->make('{$path}', {$data}, \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); } else { echo \$__env->make('{$args[0]}', {$data}, \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); } ?>";
        });

        if (function_exists('gravity_form')) {
            Blade::directive('gravityform', function ($expression) {
                return "<?php gravity_form({$expression}); ?>";
            });
        }

        View::addNamespace('theme', base_path().'/resources/views/themes/'.$this->wp_theme->get_stylesheet());

        $this->overrideThemeJsonResolver();
    }

    public function overrideThemeJsonResolver()
    {
        Filter::add('theme_file_path', function ($path, $file) {
            $blade_theme_json = base_path().'/resources/views/themes/'.wp_get_theme()->get_stylesheet().'/'.$file;

            if ($file !== 'theme.json' || ! file_exists($blade_theme_json)) {
                return $path;
            }

            return $blade_theme_json;
        }, 90, 2);
    }
}
