<?php

namespace Pollora\Hook\Commands;

use Illuminate\Support\Facades\File;

trait HookBootstrap
{
    /**
     * Ajoute une classe hookable à bootstrap/hooks.php.
     *
     * @param string $hookClass Nom complet de la classe à ajouter (avec namespace).
     * @return void
     */
    protected function addHookToBootstrap(string $hookClass): void
    {
        $bootstrapPath = base_path('bootstrap/hooks.php');

        try {
            // Charger le contenu existant ou générer une structure de base
            $content = File::exists($bootstrapPath)
                ? File::get($bootstrapPath)
                : "<?php\ndeclare(strict_types=1);\n\nreturn [\n];\n";

            // Vérifier si la classe est déjà enregistrée
            if (strpos($content, "\\{$hookClass}::class") !== false) {
                $this->warn("The class \\{$hookClass} is already registered in hooks.php.");
                return;
            }

            // Ajouter la classe avant le dernier crochet fermant
            $content = preg_replace(
                '/(\\];)$/',
                "    \\{$hookClass}::class,\n$1",
                $content
            );

            // Écrire le contenu mis à jour
            File::put($bootstrapPath, $content);
        } catch (\Exception $e) {
            $this->error('Failed to update hooks.php: ' . $e->getMessage());
        }
    }
}
