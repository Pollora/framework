<?php

declare(strict_types=1);

namespace Pollen\Theme\Commands;

use Pollen\Theme\Presets\Traits\StubTrait;
use Pollen\Theme\Presets\Traits\ViewScaffolding;
use Pollen\Theme\Presets\Vite\VitePresetExport;
use Qirolab\Theme\Enums\CssFramework;
use Qirolab\Theme\Presets\Traits\PackagesTrait;

use function Laravel\Prompts\text;

class MakeThemeCommand extends \Qirolab\Theme\Commands\MakeThemeCommand
{
    use PackagesTrait;
    use StubTrait;
    use ViewScaffolding;

    public $signature = 'make:theme {theme?}';

    public $description = 'Create a new WordPress theme';

    public $themeTitle;

    public $themeDescription;

    public $themeUri;

    public $themeAuthor;

    public $themeVersion;

    public $themeAuthorUri;

    public $themeName;

    public function handle(): void
    {
        $this->theme = $this->askTheme();
        $this->themeName = text(
            label: 'Name of your awesome theme?',
            placeholder: 'E.g. Twenty Twenty',
            default: $this->theme,
            required: true
        ) ?? $this->theme;

        $this->themeDescription = text(
            label: 'Description of your theme?',
            placeholder: 'E.g. Twenty Twenty'
        );

        $this->themeUri = text(
            label: 'Theme URI?',
            required: true,
        );

        $this->themeAuthor = text(
            label: 'Author of the theme? (maybe you ?)',
            placeholder: 'E.g. Robert Mitchum',
            required: true
        );

        $this->themeAuthorUri = text(
            label: "Author URI of the theme? (let's advertise !)",
            default: $this->themeUri,
        ) ?? $this->themeUri;

        $this->themeVersion = text(
            label: 'Theme version ?',
            placeholder: '1.0?',
            default: '1.0',
            required: true
        );

        $this->cssFramework = CssFramework::Tailwind;

        if (! $this->themeExists($this->theme)) {
            (new VitePresetExport(
                $this->theme,
                $this->themeName,
                $this->cssFramework,
            ))
                ->export();

            $this->exportViewScaffolding();

            $this->line("<options=bold>Theme slug:</options=bold> {$this->themeName}");
            $this->line("<options=bold>Theme Name:</options=bold> {$this->themeName}");
            $this->line("<options=bold>CSS Framework:</options=bold> {$this->cssFramework}");
            $this->line('');

            $this->info("Theme scaffolding installed successfully.\n");

            $themePath = $this->relativeThemePath($this->theme);
            $scriptDevCmd = '    "dev:'.$this->theme.'": "vite --config '.$themePath.'/vite.config.js",';
            $scriptBuildCmd = '    "build:'.$this->theme.'": "vite build --config '.$themePath.'/vite.config.js"';

            $this->comment('Add following line in the `<fg=blue>scripts</fg=blue>` section of the `<fg=blue>package.json</fg=blue>` file:');
            $this->line('');

            $this->line('"scripts": {', 'fg=magenta');
            $this->line('    ...', 'fg=magenta');
            $this->line('');
            $this->line($scriptDevCmd, 'fg=magenta');
            $this->line($scriptBuildCmd, 'fg=magenta');
            $this->line('}');

            $this->line('');
            $this->comment('And please run `<fg=blue>npm install && npm run dev:'.$this->theme.'</fg=blue>` to compile your fresh scaffolding.');
        }
    }

    protected function askTheme()
    {
        $theme = $this->argument('theme');

        if (! $theme) {
            $theme = text(
                label: 'Slug of your awesome theme?',
                placeholder: 'E.g. Twenty Twenty',
                required: true,
                validate: fn (string $value) => match (true) {
                    ! preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $value) => 'Please respect the slug format.',
                    default => null
                }
            );
        }

        return $theme;
    }
}
