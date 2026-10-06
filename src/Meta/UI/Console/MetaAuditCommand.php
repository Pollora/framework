<?php

declare(strict_types=1);

namespace Pollora\Meta\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\Meta\Application\Services\MetaAuditor;

/**
 * Reads the database against the #[Meta] declarations. Exits with 1 when a
 * stored value cannot be read as its type, so it can run in CI against a copy
 * of production; keys nothing declares are listed for information.
 */
#[Description('Check the stored meta against the #[Meta] declarations')]
#[Signature('pollora:meta:audit {--limit=10000 : Rows read per meta key, at most} {--json : Output as JSON}')]
class MetaAuditCommand extends Command
{
    public function handle(MetaAuditor $auditor): int
    {
        if (! function_exists('is_serialized')) {
            $this->components->error('WordPress is not loaded.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $unreadable = $auditor->unreadable($limit);
        $undeclared = $auditor->undeclared(500);

        if ($this->option('json')) {
            $this->line((string) json_encode(['unreadable' => $unreadable, 'undeclared' => $undeclared], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $unreadable === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($unreadable === []) {
            $this->components->info('Every stored value reads as its declared type.');
        } else {
            $this->components->error(sprintf('%d meta have stored values that read as the default.', count($unreadable)));

            foreach ($unreadable as $finding) {
                $this->components->twoColumnDetail(
                    sprintf('%s::$%s <fg=gray>%s</>', class_basename($finding['class']), $finding['property'], $finding['owner']),
                    sprintf('%d / %d object(s)', $finding['unreadable'], $finding['checked'])
                );
                $this->line(sprintf('    <fg=gray>%s — objects %s</>', $finding['example'], implode(', ', $finding['objects'])));
            }
        }

        if ($undeclared !== []) {
            $this->newLine();
            $this->components->warn(sprintf('%d key(s) stored on your post types and taxonomies are declared by no #[Meta]: left by a renamed property, or written by a plugin.', count($undeclared)));

            foreach ($undeclared as $finding) {
                $this->components->twoColumnDetail(sprintf('%s <fg=gray>%s</>', $finding['key'], $finding['owner']), sprintf('%d object(s)', $finding['objects']));
            }
        }

        return $unreadable === [] ? self::SUCCESS : self::FAILURE;
    }
}
