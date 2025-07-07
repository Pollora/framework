<?php

declare(strict_types=1);

namespace Pollora\Theme\UI\Console\Commands;

use Illuminate\Console\Command;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;
use Pollora\Theme\Domain\Contracts\ThemeService;

/**
 * Simplified command to display theme registration status.
 */
class ThemeStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'pollora:theme:status';

    /**
     * The console command description.
     */
    protected $description = 'Display the current theme registration status';

    /**
     * Execute the console command.
     */
    public function handle(
        ThemeRegistrarInterface $registrar,
        ThemeService $themeService
    ): int {
        $this->displayHeader();
        $this->displayRegisteredTheme($registrar);
        $this->displayThemeServiceStatus($registrar, $themeService);
        $this->displayWordPressInfo();

        return Command::SUCCESS;
    }

    protected function displayHeader(): void
    {
        $this->info('Theme Registration Status');
        $this->line('==========================');
    }

    protected function displayRegisteredTheme(ThemeRegistrarInterface $registrar): void
    {
        $registeredTheme = $registrar->getActiveTheme();

        if ($registeredTheme instanceof \Pollora\Theme\Domain\Contracts\ThemeModuleInterface) {
            $this->info('✓ Self-registered theme found:');
            $this->line("  Name: {$registeredTheme->getName()}");
            $this->line("  Path: {$registeredTheme->getPath()}");
            $this->line('  Enabled: '.($registeredTheme->isEnabled() ? 'Yes' : 'No'));

            $this->displayThemeHeaders($registeredTheme);
        } else {
            $this->warn('✗ No self-registered theme found');
        }

        $this->newLine();
    }

    protected function displayThemeHeaders($theme): void
    {
        if (! method_exists($theme, 'getHeaders')) {
            return;
        }

        $headers = $theme->getHeaders();

        if (! empty($headers['Name'])) {
            $this->line("  Display Name: {$headers['Name']}");
        }

        if (! empty($headers['Version'])) {
            $this->line("  Version: {$headers['Version']}");
        }
    }

    protected function displayThemeServiceStatus(
        ThemeRegistrarInterface $registrar,
        ThemeService $themeService
    ): void {
        $activeTheme = $themeService->getActiveTheme();

        if ($activeTheme) {
            $this->info('✓ Active theme via ThemeService:');
            $this->line("  Name: {$activeTheme->getName()}");
            $this->line("  Path: {$activeTheme->getPath()}");

            $this->checkSynchronization($registrar, $activeTheme);
        } else {
            $this->warn('✗ No active theme found via ThemeService');
        }

        $this->newLine();
    }

    protected function checkSynchronization(ThemeRegistrarInterface $registrar, $activeTheme): void
    {
        $registeredTheme = $registrar->getActiveTheme();

        if ($registeredTheme && $activeTheme->getName() === $registeredTheme->getName()) {
            $this->info('  ✓ Theme service and registrar are in sync');
        } else {
            $this->warn('  ✗ Theme service and registrar are out of sync');
        }
    }

    protected function displayWordPressInfo(): void
    {
        if (! function_exists('get_stylesheet') || ! function_exists('get_template_directory')) {
            return;
        }

        $this->info('WordPress theme info:');
        $this->line('  Stylesheet: '.get_stylesheet());
        $this->line('  Template Directory: '.get_template_directory());
    }
}
