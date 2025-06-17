<?php

declare(strict_types=1);

namespace Pollora\Theme\UI\Console\Commands;

use Illuminate\Console\Command;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;
use Pollora\Theme\Domain\Contracts\ThemeService;

/**
 * Command to display theme registration status.
 *
 * This command helps debug and verify the theme self-registration system.
 */
class ThemeStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'theme:status';

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
        $this->info('Theme Registration Status');
        $this->line('==========================');

        // Check self-registered theme
        $registeredTheme = $registrar->getActiveTheme();
        if ($registeredTheme) {
            $this->info('✓ Self-registered theme found:');
            $this->line("  Name: {$registeredTheme->getName()}");
            $this->line("  Path: {$registeredTheme->getPath()}");
            $this->line("  Enabled: " . ($registeredTheme->isEnabled() ? 'Yes' : 'No'));
            
            if (method_exists($registeredTheme, 'getHeaders')) {
                $headers = $registeredTheme->getHeaders();
                if (!empty($headers['Name'])) {
                    $this->line("  Display Name: {$headers['Name']}");
                }
                if (!empty($headers['Version'])) {
                    $this->line("  Version: {$headers['Version']}");
                }
            }
        } else {
            $this->warn('✗ No self-registered theme found');
        }

        $this->newLine();

        // Check theme service active theme (should be the same as self-registered)
        $activeTheme = $themeService->getActiveTheme();
        if ($activeTheme) {
            $this->info('✓ Active theme via ThemeService:');
            $this->line("  Name: {$activeTheme->getName()}");
            $this->line("  Path: {$activeTheme->getPath()}");
            
            if ($registeredTheme && $activeTheme->getName() === $registeredTheme->getName()) {
                $this->info('  ✓ Theme service and registrar are in sync');
            } else {
                $this->warn('  ✗ Theme service and registrar are out of sync');
            }
        } else {
            $this->warn('✗ No active theme found via ThemeService');
        }

        $this->newLine();

        // WordPress theme info
        if (function_exists('get_stylesheet') && function_exists('get_template_directory')) {
            $this->info('WordPress theme info:');
            $this->line("  Stylesheet: " . get_stylesheet());
            $this->line("  Template Directory: " . get_template_directory());
        }

        return Command::SUCCESS;
    }
} 