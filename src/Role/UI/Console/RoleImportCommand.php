<?php

declare(strict_types=1);

namespace Pollora\Role\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Support\Str;
use Pollora\Console\AbstractGeneratorCommand;
use Pollora\Role\Application\Services\RoleDefinitionBuilder;
use Pollora\Role\Application\Services\RoleRegistry;
use Pollora\Role\Domain\Contracts\RoleStoreInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Turns a role stored in the database (made by `add_role()` or a role editor
 * plugin) into a `#[Role]` class: the first step from a role that drifts
 * between environments to one the code owns.
 */
#[Description('Generate a #[Role] class from a role stored in the database')]
class RoleImportCommand extends AbstractGeneratorCommand
{
    protected $name = 'pollora:roles:import';

    protected $type = 'Role';

    protected string $subPath = 'Cms/Roles';

    /** Whether the role was refused, for the exit code */
    private bool $refused = false;

    /** @var array{slug: string, label: string, inherits: string|null, grants: list<string>, without: list<string>, denied: list<string>} */
    private array $import = ['slug' => '', 'label' => '', 'inherits' => null, 'grants' => [], 'without' => [], 'denied' => []];

    public function handle(): ?bool
    {
        $slug = (string) $this->argument('role');
        $stored = $this->laravel->make(RoleStoreInterface::class)->storedRoles();
        $inherits = $this->option('inherits');
        $inherits = is_string($inherits) && $inherits !== '' ? $inherits : null;

        $refusal = match (true) {
            in_array($slug, RoleDefinitionBuilder::CORE_ROLES, true) => sprintf('"%s" is a core role: change it with a #[ModifyRole(\'%s\')] class instead.', $slug, $slug),
            ! isset($stored[$slug]) => sprintf('No role "%s" is stored in the database.', $slug),
            $this->isDeclared($slug) => sprintf('The role "%s" is already declared in the code.', $slug),
            $inherits !== null && ! isset($stored[$inherits]) => sprintf('No role "%s" is stored in the database to inherit from.', $inherits),
            default => null,
        };

        if ($refusal !== null) {
            $this->components->error($refusal);
            $this->refused = true;

            return false;
        }

        $capabilities = (array) ($stored[$slug]['capabilities'] ?? []);
        $granted = array_keys(array_filter($capabilities));
        $parent = $inherits === null ? [] : array_keys(array_filter((array) ($stored[$inherits]['capabilities'] ?? [])));

        $this->import = [
            'slug' => $slug,
            'label' => (string) ($stored[$slug]['name'] ?? Str::headline($slug)),
            'inherits' => $inherits,
            'grants' => array_values(array_diff($granted, $parent)),
            'without' => array_values(array_diff($parent, $granted)),
            'denied' => array_keys(array_filter($capabilities, static fn (mixed $value): bool => ! $value)),
        ];

        return parent::handle();
    }

    /**
     * A generator's handle() returns false on refusal, which Laravel turns into exit code 0.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = parent::execute($input, $output);

        return $this->refused ? self::FAILURE : $code;
    }

    protected function getStub(): string
    {
        return __DIR__.'/stubs/role-import.stub';
    }

    /**
     * The class name: --class, or the role slug in StudlyCase.
     */
    protected function getNameInput(): string
    {
        $class = $this->option('class');

        return is_string($class) && $class !== '' ? $class : Str::studly((string) $this->argument('role'));
    }

    protected function getArguments(): array
    {
        return [
            ['role', InputArgument::REQUIRED, 'The slug of the role stored in the database'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ...parent::getOptions(),
            ['inherits', null, InputOption::VALUE_REQUIRED, 'A role to inherit from: only the differences are written'],
            ['class', null, InputOption::VALUE_REQUIRED, 'The class name (default: the slug in StudlyCase)'],
        ];
    }

    protected function replaceClass($stub, $name): string
    {
        $stub = parent::replaceClass($stub, $name);
        $import = $this->import;
        $sensitive = array_values(array_intersect($import['grants'], RoleDefinitionBuilder::SENSITIVE_CAPABILITIES));

        $roleArguments = [var_export($import['slug'], true), 'label: '.var_export($import['label'], true)];

        if ($import['inherits'] !== null) {
            $roleArguments[] = 'inherits: '.var_export($import['inherits'], true);
        }

        if ($sensitive !== []) {
            $roleArguments[] = 'allowSensitive: true';
        }

        $attributes = [sprintf('#[Role(%s)]', implode(', ', $roleArguments))];
        $imports = ['use Pollora\\Attributes\\Role;'];

        if ($import['grants'] !== []) {
            $attributes[] = $this->capabilityAttribute('Grants', $import['grants']);
            $imports[] = 'use Pollora\\Attributes\\Role\\Grants;';
        }

        if ($import['without'] !== []) {
            $attributes[] = $this->capabilityAttribute('Without', $import['without']);
            $imports[] = 'use Pollora\\Attributes\\Role\\Without;';
        }

        $notes = '';

        if ($sensitive !== []) {
            $notes .= "\n *\n * Grants capabilities that let a user take over the site: ".implode(', ', $sensitive).'.';
        }

        if ($import['denied'] !== []) {
            $notes .= "\n *\n * Stored as denied (false) and left out, since a role only grants or removes: ".implode(', ', $import['denied']).'.';
        }

        sort($imports);

        return str_replace(
            ['DummyImports', 'DummyLabel', 'DummyNotes', 'DummyAttributes'],
            [implode("\n", $imports), $import['label'], $notes, implode("\n", $attributes)],
            $stub
        );
    }

    /**
     * @param  list<string>  $capabilities
     */
    private function capabilityAttribute(string $attribute, array $capabilities): string
    {
        sort($capabilities);

        return sprintf("#[%s(\n    %s,\n)]", $attribute, implode(",\n    ", array_map(static fn (string $capability): string => var_export($capability, true), $capabilities)));
    }

    private function isDeclared(string $slug): bool
    {
        foreach ($this->laravel->make(RoleRegistry::class)->definitions() as $definition) {
            if ($definition->slug === $slug) {
                return true;
            }
        }

        return false;
    }
}
