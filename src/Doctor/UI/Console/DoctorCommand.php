<?php

declare(strict_types=1);

namespace Pollora\Doctor\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\Doctor\Application\Services\Doctor;
use Pollora\Doctor\Domain\Enums\CheckStatus;
use Pollora\Doctor\Domain\Enums\RunContext;

/**
 * The health counterpart of pollora:status: status says what is there, doctor
 * says whether it works, and what to run when it does not.
 *
 * Exits 1 when a check finds an error, so it can gate a deploy or a CI job.
 * Checks that only mean something in a web request run in Tools › Site Health.
 */
#[Description('Check the Pollora project for known silent failures, and say how to fix them')]
#[Signature('pollora:doctor {--json : Output as JSON}')]
final class DoctorCommand extends Command
{
    private const array SYMBOLS = [
        'ok' => '<fg=green>✓</>',
        'warning' => '<fg=yellow>!</>',
        'error' => '<fg=red>✗</>',
        'skipped' => '<fg=gray>–</>',
    ];

    public function handle(Doctor $doctor): int
    {
        $report = $doctor->run(RunContext::Console);

        $counts = array_count_values(array_map(fn (array $entry): string => $entry['result']->status->value, $report));
        $errors = $counts[CheckStatus::Error->value] ?? 0;
        $warnings = $counts[CheckStatus::Warning->value] ?? 0;

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'errors' => $errors,
                'warnings' => $warnings,
                'checks' => array_map(fn (array $entry): array => [
                    'id' => $entry['check']->id(),
                    'label' => $entry['check']->label(),
                    ...$entry['result']->toArray(),
                ], $report),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $errors > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->newLine();

        foreach ($report as ['check' => $check, 'result' => $result]) {
            $this->line(sprintf('  %s <options=bold>%s</> — %s', self::SYMBOLS[$result->status->value], $check->label(), $result->summary));

            foreach ($result->details as $detail) {
                $this->line('      <fg=gray>'.$detail.'</>');
            }

            if ($result->fix !== null && in_array($result->status, [CheckStatus::Error, CheckStatus::Warning], true)) {
                $this->line('      <fg=cyan>→ '.$result->fix.'</>');
            }
        }

        $this->newLine();
        $this->line(sprintf('  %d error(s), %d warning(s).', $errors, $warnings));
        $this->line('  <fg=gray>Checks that only a web request can make run in Tools › Site Health, in wp-admin.</>');
        $this->newLine();

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
