<?php

declare(strict_types=1);

namespace Pollen\Gutenberg;

use Illuminate\Support\ServiceProvider;
use Pollen\Foundation\Application;
use Pollen\Support\Facades\Action;

class PatternServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function register()
    {
        Action::add('init', function () {
            if (wp_installing()) {
                return;
            }
            $pattern = new Pattern($this->app);
            $pattern->registerThemeBlockPatterns();
            $pattern->registerThemeBlockPatternCategories();
        });
    }
}
