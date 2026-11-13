<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Pollora\Theme\Domain\Exceptions\ThemeSetupException;
use Pollora\Theme\UI\Console\BaseThemeCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;

function baseThemeCommand(string $themesDir, string $name = 'demo'): BaseThemeCommand
{
    $command = new class(new Repository(['theme' => ['directory' => $themesDir]]), new Filesystem) extends BaseThemeCommand
    {
        public function themesPath(): string
        {
            return $this->getThemesPath();
        }

        public function exists(): bool
        {
            return $this->directoryExists();
        }

        public function write(string $path, string $content = ''): void
        {
            $this->makeFile($path, $content);
        }

        public function render(string $template, array $replacements = []): string
        {
            return $this->fromTemplate($template, $replacements);
        }

        public function templatePath(string $template): ?string
        {
            return $this->getTemplatePath($template);
        }
    };
    $command->setDefinition(new InputDefinition([new InputArgument('name')]));
    $command->setInput(new ArrayInput(['name' => $name], $command->getDefinition()));

    return $command;
}

beforeEach(function (): void {
    // getThemesPath() evaluates base_path() as a default argument, whether or not it is used.
    $this->previousContainer = Container::getInstance();
    Container::setInstance(new class extends Container
    {
        public function basePath($path = ''): string
        {
            return '/srv/site'.($path === '' ? '' : DIRECTORY_SEPARATOR.$path);
        }
    });
    $this->dir = sys_get_temp_dir().'/pollora-basecmd-'.bin2hex(random_bytes(6));
    mkdir($this->dir);
});

afterEach(function (): void {
    Container::setInstance($this->previousContainer);
    (new Filesystem)->deleteDirectory($this->dir);
});

describe('BaseThemeCommand', function (): void {
    it('reads the themes directory from theme.directory', function (): void {
        expect(baseThemeCommand($this->dir)->themesPath())->toBe($this->dir);
    });

    it('reports whether the named theme directory exists', function (): void {
        $command = baseThemeCommand($this->dir);

        expect($command->exists())->toBeFalse();

        mkdir($this->dir.'/demo');

        expect($command->exists())->toBeTrue();
    });

    it('writes a file inside the theme, creating the directories on the way', function (): void {
        baseThemeCommand($this->dir)->write('resources/views/index.blade.php', '<h1>Hi</h1>');

        expect(file_get_contents($this->dir.'/demo/resources/views/index.blade.php'))->toBe('<h1>Hi</h1>');
    });

    it('finds no bundled template where the framework ships none', function (): void {
        expect(baseThemeCommand($this->dir)->templatePath('definitely-not-bundled'))->toBeNull();
    });

    it('refuses to render a stub that is not bundled', function (): void {
        baseThemeCommand($this->dir)->render('definitely-not-bundled');
    })->throws(ThemeSetupException::class, 'The stub template "definitely-not-bundled" is not bundled with the framework');
});
