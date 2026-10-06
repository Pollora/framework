<?php

declare(strict_types=1);

namespace Pollora\Meta\UI\Console;

use BackedEnum;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\Meta\Application\Services\MetaSchemaRepository;
use Pollora\Meta\Domain\Enums\MetaValueType;
use Pollora\Meta\Domain\Models\MetaDefinition;

/**
 * Lists the typed meta the project declares: by class, the object they belong
 * to, and each meta with its key, type and options.
 */
#[Description('List the typed meta declared with #[Meta]')]
#[Signature('pollora:meta:list {--json : Output as JSON}')]
class MetaListCommand extends Command
{
    public function handle(MetaSchemaRepository $schemas): int
    {
        $list = [];

        foreach ($schemas->all() as $schema) {
            $list[] = [
                'class' => $schema->declaringClass,
                'owner' => $schema->ownerName(),
                'meta' => array_values(array_map($this->describe(...), $schema->definitions)),
            ];
        }

        $failures = $schemas->failures();

        if ($this->option('json')) {
            $this->line((string) json_encode(['schemas' => $list, 'refused' => $failures], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $failures === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($list === [] && $failures === []) {
            $this->components->info('No #[Meta] declared.');

            return self::SUCCESS;
        }

        foreach ($list as $schema) {
            $this->components->twoColumnDetail(sprintf('<fg=green;options=bold>%s</>', $schema['class']), $schema['owner']);

            foreach ($schema['meta'] as $meta) {
                $this->components->twoColumnDetail(
                    sprintf('  %s <fg=gray>$%s</>', $meta['key'], $meta['property']),
                    $meta['type'].($meta['flags'] === [] ? '' : ' <fg=gray>'.implode(', ', $meta['flags']).'</>')
                );
            }
        }

        foreach ($failures as $class => $reason) {
            $this->components->error($class.': '.$reason);
        }

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{property: string, key: string, type: string, default: mixed, flags: list<string>}
     */
    private function describe(MetaDefinition $definition): array
    {
        $flags = array_values(array_filter([
            $definition->showInRest ? 'rest' : null,
            $definition->single ? null : 'one row per item',
            $definition->media ? 'media' : null,
            $definition->public ? 'public' : null,
            $definition->revisions ? 'revisions' : null,
            $definition->capability === null ? null : 'capability: '.$definition->capability,
            $definition->rules === [] ? null : 'rules: '.implode('|', array_map(static fn (mixed $rule): string => is_string($rule) ? $rule : get_debug_type($rule), $definition->rules)),
        ]));

        return [
            'property' => $definition->property,
            'key' => $definition->key,
            'type' => $this->typeOf($definition),
            'default' => $definition->default instanceof BackedEnum ? $definition->default->value : (is_object($definition->default) ? get_debug_type($definition->default) : $definition->default),
            'flags' => $flags,
        ];
    }

    private function typeOf(MetaDefinition $definition): string
    {
        $type = match ($definition->valueType) {
            MetaValueType::String => 'string',
            MetaValueType::Integer => 'int',
            MetaValueType::Number => 'float',
            MetaValueType::Boolean => 'bool',
            MetaValueType::DateTime, MetaValueType::Enum, MetaValueType::DataObject => class_basename((string) $definition->valueClass),
            MetaValueType::ArrayOf => $this->typeOf($definition->item()).'[]',
        };

        return ($definition->nullable ? '?' : '').$type;
    }
}
