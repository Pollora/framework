<?php

declare(strict_types=1);

namespace Pollora\Role\Infrastructure\Checks;

use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Role\Domain\Contracts\RoleUsageInterface;
use Pollora\Role\Domain\Services\StoredEntryClassifier;
use Pollora\Role\Infrastructure\Adapters\WordPressRoleInjector;

/**
 * The roles users carry exist, new users get a role that exists, and every
 * declaration was applied.
 *
 * A role removed from the code is gone from WordPress, but the users who
 * carried it keep it in their profile, with no capability from it; a
 * `default_role` naming it gives every new user nothing. Neither shows an
 * error: users simply cannot do what they could.
 */
final readonly class RolesCheck implements CheckInterface
{
    /** Users named per entry, at most. */
    private const int SAMPLE = 5;

    public function __construct(
        private RoleUsageInterface $usage,
        private WordPressRoleInjector $injector,
        private StoredEntryClassifier $classifier = new StoredEntryClassifier,
    ) {}

    public function id(): string
    {
        return 'roles';
    }

    public function label(): string
    {
        return 'Roles';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        if (! function_exists('wp_roles') || \did_action('init') === 0) {
            return CheckResult::skipped('WordPress is not loaded.');
        }

        $roles = \wp_roles()->roles;

        $errors = [];
        $warnings = [];
        $defaultRole = (string) \get_option('default_role');

        if ($defaultRole !== '' && ! isset($roles[$defaultRole])) {
            $errors[] = sprintf('default_role is "%s", a role WordPress does not know: new users get no capability', $defaultRole);
        }

        $individual = [];

        foreach ($this->usage->entries() as $entry => $users) {
            $kind = $this->classifier->classify($entry, $roles);

            if ($kind === StoredEntryClassifier::ROLE) {
                continue;
            }

            if ($kind === StoredEntryClassifier::CAPABILITY) {
                $individual[] = sprintf('%s (%d user(s))', $entry, $users);

                continue;
            }

            $warnings[] = sprintf(
                '%d user(s) carry "%s", which is no role WordPress knows and no capability a role grants — a role removed from the code, most likely: %s',
                $users,
                $entry,
                implode(', ', array_slice($this->usage->usersWith($entry), 0, self::SAMPLE))
            );
        }

        if ($individual !== []) {
            $warnings[] = 'Capabilities given to users one by one, outside the roles the code declares: '.implode(', ', $individual);
        }

        foreach ($this->injector->warnings() as $warning) {
            $warnings[] = 'Not applied: '.$warning;
        }

        if ($errors !== []) {
            return CheckResult::error(
                'New users get a role that does not exist.',
                [...$errors, ...$warnings],
                'Set default_role (Settings › General, or wp option update default_role subscriber) to a role that exists',
            );
        }

        if ($warnings !== []) {
            return CheckResult::warning(
                sprintf('%d problem(s) with roles.', count($warnings)),
                $warnings,
                'Roles removed from the code: php artisan pollora:roles:prune --reassign=subscriber (shows the changes first). Capabilities given one by one: declare them on a role with #[Role] or #[ModifyRole], then remove them from the users',
            );
        }

        return CheckResult::ok(sprintf('%d role(s); every role users carry exists.', count($roles)));
    }
}
