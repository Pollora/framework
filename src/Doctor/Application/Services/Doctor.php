<?php

declare(strict_types=1);

namespace Pollora\Doctor\Application\Services;

use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Throwable;

/**
 * Runs the checks that belong to a context.
 *
 * A check that throws is reported as an error of that check, with the exception's
 * message: one broken check must not hide the verdict of all the others.
 */
final readonly class Doctor
{
    /**
     * @param  iterable<CheckInterface>  $checks
     */
    public function __construct(private iterable $checks) {}

    /**
     * @return list<CheckInterface>
     */
    public function checksFor(RunContext $context): array
    {
        $checks = [];

        foreach ($this->checks as $check) {
            if (in_array($context, $check->runsIn(), true)) {
                $checks[] = $check;
            }
        }

        return $checks;
    }

    public function runCheck(CheckInterface $check, RunContext $context): CheckResult
    {
        try {
            return $check->run($context);
        } catch (Throwable $throwable) {
            return CheckResult::error(
                'The check itself failed: '.$throwable->getMessage(),
                [$throwable::class.' in '.$throwable->getFile().':'.$throwable->getLine()],
            );
        }
    }

    /**
     * @return list<array{check: CheckInterface, result: CheckResult}>
     */
    public function run(RunContext $context): array
    {
        return array_map(
            fn (CheckInterface $check): array => ['check' => $check, 'result' => $this->runCheck($check, $context)],
            $this->checksFor($context),
        );
    }
}
