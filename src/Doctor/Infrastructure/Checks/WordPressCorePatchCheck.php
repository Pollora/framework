<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Closure;
use Pollora\Dashboard\Domain\Services\SystemInfoCollector;
use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;

/**
 * WordPress core carries Pollora's patch — its `__()` renamed `__wp()` — and `__()`
 * is Pollora's.
 *
 * Found in September: with a missing or stale patches.lock.json, composer-patches 2
 * skipped the patch and `composer install` still exited 0. Laravel's translator and
 * Pollora's `__()` were then lost without a word.
 */
final readonly class WordPressCorePatchCheck implements CheckInterface
{
    /**
     * @param  string|null  $includesDirectory  WordPress's wp-includes; ABSPATH.WPINC when null
     * @param  (Closure(): array{override_active: bool, helper_file: string})|null  $translationInfo  who owns __(); the dashboard's collector when null
     */
    public function __construct(
        private ?string $includesDirectory = null,
        private ?Closure $translationInfo = null,
    ) {}

    public function id(): string
    {
        return 'wordpress-core-patch';
    }

    public function label(): string
    {
        return 'WordPress core patch';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        $includes = $this->includesDirectory ?? (defined('ABSPATH') && defined('WPINC') ? ABSPATH.WPINC : null);
        $file = $includes === null ? null : rtrim($includes, '/').'/l10n.php';

        if ($file === null || ! is_file($file)) {
            return CheckResult::skipped('WordPress is not loaded.');
        }

        if (! str_contains((string) file_get_contents($file), 'function __wp(')) {
            return CheckResult::error(
                "WordPress core is not patched: its __() still takes the place of Laravel's and Pollora's.",
                [$file.' declares __(), not __wp()'],
                'composer patches-relock && composer patches-repatch',
            );
        }

        $translation = $this->translationInfo instanceof Closure
            ? ($this->translationInfo)()
            : resolve(SystemInfoCollector::class)->collectTranslationInfo();

        if (! $translation['override_active']) {
            return CheckResult::warning(
                "The core is patched, but __() is not Pollora's: WordPress translations through __() are lost.",
                ['__() is declared in '.$translation['helper_file']],
                'Check that pollora/helper-overrider is installed, then run composer dump-autoload',
            );
        }

        return CheckResult::ok("The core is patched, and __() is Pollora's.");
    }
}
