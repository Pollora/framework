<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Pollora\Doctor\Application\Services\Doctor;
use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Doctor\UI\Console\DoctorCommand;
use Pollora\Doctor\UI\Http\SiteHealthTests;

/**
 * A check answering what it is given, in the contexts it is given.
 *
 * @param  list<RunContext>  $contexts
 */
function fakeCheck(string $id, CheckResult|Throwable $result, array $contexts = [RunContext::Console, RunContext::Http]): CheckInterface
{
    return new readonly class($id, $result, $contexts) implements CheckInterface
    {
        /** @param list<RunContext> $contexts */
        public function __construct(private string $id, private CheckResult|Throwable $result, private array $contexts) {}

        public function id(): string
        {
            return $this->id;
        }

        public function label(): string
        {
            return ucfirst(str_replace('-', ' ', $this->id));
        }

        public function runsIn(): array
        {
            return $this->contexts;
        }

        public function run(RunContext $context): CheckResult
        {
            if ($this->result instanceof Throwable) {
                throw $this->result;
            }

            return $this->result;
        }
    };
}

/** Run pollora:doctor on these checks. @return array{exit: int, output: string} */
function runDoctor(array $checks, array $options = []): array
{
    app()->instance(Doctor::class, new Doctor($checks));
    resolve(Kernel::class)->registerCommand(resolve(DoctorCommand::class));

    $exit = Artisan::call('pollora:doctor', $options);

    return ['exit' => $exit, 'output' => Artisan::output()];
}

describe('Doctor', function (): void {
    it('runs only the checks of the context', function (): void {
        $doctor = new Doctor([
            fakeCheck('both', CheckResult::ok('fine')),
            fakeCheck('web-only', CheckResult::ok('fine'), [RunContext::Http]),
        ]);

        expect(array_map(fn (CheckInterface $check): string => $check->id(), $doctor->checksFor(RunContext::Console)))->toBe(['both'])
            ->and($doctor->checksFor(RunContext::Http))->toHaveCount(2);
    });

    it('reports a check that throws as an error of that check, and runs the others', function (): void {
        $report = (new Doctor([
            fakeCheck('broken', new RuntimeException('cannot read the lock')),
            fakeCheck('fine', CheckResult::ok('fine')),
        ]))->run(RunContext::Console);

        expect($report[0]['result']->status->value)->toBe('error')
            ->and($report[0]['result']->summary)->toContain('cannot read the lock')
            ->and($report[1]['result']->status->value)->toBe('ok');
    });
});

describe('pollora:doctor', function (): void {
    it('exits 1 on an error, and prints the fix under it', function (): void {
        $result = runDoctor([fakeCheck('patches-lock', CheckResult::error('The lock is stale.', ['a: b'], 'composer patches-relock'))]);

        expect($result['exit'])->toBe(1)
            ->and($result['output'])->toContain('The lock is stale.')
            ->and($result['output'])->toContain('a: b')
            ->and($result['output'])->toContain('→ composer patches-relock')
            ->and($result['output'])->toContain('1 error(s), 0 warning(s)');
    });

    it('exits 0 on warnings alone', function (): void {
        expect(runDoctor([fakeCheck('pattern-cache', CheckResult::warning('Stale.', [], 'wp eval …'))])['exit'])->toBe(0);
    });

    it('does not run a check that needs a web request', function (): void {
        $result = runDoctor([fakeCheck('block-registration', CheckResult::error('Missing blocks.'), [RunContext::Http])]);

        expect($result['exit'])->toBe(0)
            ->and($result['output'])->not->toContain('Missing blocks.');
    });

    it('prints JSON with --json', function (): void {
        $result = runDoctor([fakeCheck('theme-build', CheckResult::error('Not built.', [], 'npm run build'))], ['--json' => true]);
        $json = json_decode($result['output'], true);

        expect($result['exit'])->toBe(1)
            ->and($json['errors'])->toBe(1)
            ->and($json['checks'][0])->toMatchArray(['id' => 'theme-build', 'status' => 'error', 'summary' => 'Not built.', 'fix' => 'npm run build']);
    });
});

describe('Site Health', function (): void {
    it('adds one direct test per check that runs in a web request', function (): void {
        $tests = (new SiteHealthTests(new Doctor([
            fakeCheck('theme-build', CheckResult::ok('Built.')),
            fakeCheck('discovery-cache', CheckResult::ok('Fine.'), [RunContext::Console]),
        ])))->register(['direct' => [], 'async' => []]);

        expect(array_keys($tests['direct']))->toBe(['pollora_theme_build']);
    });

    it('turns an error into a critical result that carries the fix', function (): void {
        $check = fakeCheck('patches-lock', CheckResult::error('The lock is stale.', ['a: b'], 'composer patches-relock'));
        $tests = (new SiteHealthTests(new Doctor([$check])))->register(['direct' => []]);
        $result = ($tests['direct']['pollora_patches_lock']['test'])();

        expect($result['status'])->toBe('critical')
            ->and($result['badge']['label'])->toBe('Pollora')
            ->and($result['label'])->toContain('The lock is stale.')
            ->and($result['description'])->toContain('a: b')
            ->and($result['actions'])->toContain('composer patches-relock');
    });

    it('maps a warning to recommended and an ok to good', function (): void {
        $health = new SiteHealthTests(new Doctor([]));
        $check = fakeCheck('x', CheckResult::ok('fine'));

        expect($health->result($check, CheckResult::warning('meh'))['status'])->toBe('recommended')
            ->and($health->result($check, CheckResult::ok('fine'))['status'])->toBe('good');
    });
});
