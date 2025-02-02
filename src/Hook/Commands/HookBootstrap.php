<?php

declare(strict_types=1);

namespace Pollora\Hook\Commands;

use Illuminate\Support\Facades\File;

trait HookBootstrap
{
    /**
     * Adds a hookable class to bootstrap/hooks.php.
     *
     * @param  string  $hookClass  Fully qualified name of the class to add (with namespace).
     */
    protected function addHookToBootstrap(string $hookClass): void
    {
        $bootstrapPath = base_path('bootstrap/hooks.php');

        try {
            // Load existing content or generate a basic structure
            $content = File::exists($bootstrapPath)
                ? File::get($bootstrapPath)
                : "<?php\ndeclare(strict_types=1);\n\nreturn [\n];\n";

            // Check if the class is already registered
            if (strpos($content, "\\{$hookClass}::class") !== false) {
                $this->warn("The class \\{$hookClass} is already registered in hooks.php.");

                return;
            }

            // Add the class before the last closing bracket
            $content = preg_replace(
                '/(\\];)$/',
                "    \\{$hookClass}::class,\n$1",
                $content
            );

            // Write the updated content
            File::put($bootstrapPath, $content);
        } catch (\Exception $e) {
            $this->error('Failed to update hooks.php: '.$e->getMessage());
        }
    }
}
