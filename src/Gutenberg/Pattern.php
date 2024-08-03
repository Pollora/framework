<?php

declare(strict_types=1);

namespace Pollen\Gutenberg;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Pollen\Support\Facades\Action;
use Pollen\Theme\Contracts\ThemeComponent;

class Pattern implements ThemeComponent
{
    private const PATTERN_DIRECTORY = '/views/patterns/';

    private const PATTERN_FILE_EXTENSION = '.blade.php';

    protected array $defaultHeaders = [
        'title' => 'Title',
        'slug' => 'Slug',
        'description' => 'Description',
        'viewportWidth' => 'Viewport Width',
        'categories' => 'Categories',
        'keywords' => 'Keywords',
        'blockTypes' => 'Block Types',
        'postTypes' => 'Post Types',
        'inserter' => 'Inserter',
    ];

    public function register(): void
    {
        Action::add('init', function () {
            if (wp_installing()) {
                return;
            }
            $this->registerThemeBlockPatterns();
            $this->registerThemeBlockPatternCategories();
        });
    }

    public function registerThemeBlockPatternCategories(): void
    {
        collect(config('theme.gutenberg.categories.patterns'))
            ->each(fn ($args, $key) => register_block_pattern_category($key, $args));
    }

    public function registerThemeBlockPatterns(): void
    {
        $themes = $this->getThemes();

        foreach ($themes as $theme) {
            $dirpath = $theme->get_stylesheet_directory().self::PATTERN_DIRECTORY;

            if (! File::isDirectory($dirpath)) {
                continue;
            }

            collect(File::glob($dirpath.'*'.self::PATTERN_FILE_EXTENSION))
                ->each(fn ($file) => $this->registerPattern($file, $theme));
        }
    }

    protected function getThemes(): array
    {
        $stylesheet = get_stylesheet();
        $template = get_template();

        return collect([$stylesheet, $template])
            ->unique()
            ->map(fn ($theme) => wp_get_theme($theme))
            ->all();
    }

    protected function registerPattern(string $file, \WP_Theme $theme): void
    {
        $patternData = $this->getPatternData($file);

        if (! $this->validatePatternData($patternData, $file)) {
            return;
        }

        $patternData = $this->processPatternData($patternData, $theme);

        $content = $this->getPatternContent($file);

        if (empty($content)) {
            return;
        }

        $patternData['content'] = $content;

        register_block_pattern($patternData['slug'], $patternData);
    }

    protected function getPatternData(string $file): array
    {
        return get_file_data($file, $this->defaultHeaders);
    }

    private function isValidPattern(array $patternData): bool
    {
        return ! empty($patternData['slug']) && ! empty($patternData['title']);
    }

    private function isPatternRegistered(string $slug): bool
    {
        return \WP_Block_Patterns_Registry::get_instance()->is_registered($slug);
    }

    protected function validatePatternData(array $patternData, string $file): bool
    {
        if (! $this->isValidPattern($patternData)) {
            $this->logPatternError($file, $patternData);

            return false;
        }

        return ! $this->isPatternRegistered($patternData['slug']);
    }

    protected function processPatternData(array $patternData, \WP_Theme $theme): array
    {
        $arrayProperties = ['categories', 'keywords', 'blockTypes', 'postTypes'];

        return collect($patternData)
            ->map(function ($value, $key) use ($arrayProperties, $theme) {
                if (in_array($key, $arrayProperties)) {
                    return $value ? explode(',', $value) : null;
                }
                if ($key === 'viewportWidth') {
                    return $value ? (int) $value : null;
                }
                if ($key === 'inserter') {
                    return $value ? in_array(strtolower($value), ['yes', 'true']) : null;
                }
                if (in_array($key, ['title', 'description'])) {
                    $context = $key === 'title' ? 'Pattern title' : 'Pattern description';

                    return translate_with_gettext_context($value, $context, $theme->get('TextDomain'));
                }

                return $value;
            })
            ->filter()
            ->all();
    }

    protected function getPatternContent(string $file): ?string
    {
        $viewName = Str::replaceLast(self::PATTERN_FILE_EXTENSION, '', Str::after($file, 'views/'));

        return View::exists($viewName) ? View::make($viewName)->render() : null;
    }

    protected function logPatternError(string $file, array $patternData): void
    {
        $message = empty($patternData['slug'])
            ? __('Could not register file "%s" as a block pattern ("Slug" field missing)')
            : __('Could not register file "%s" as a block pattern ("Title" field missing)');

        _doing_it_wrong('_register_theme_block_patterns', sprintf($message, $file), '6.0.0');
    }
}
