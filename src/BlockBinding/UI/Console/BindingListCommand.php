<?php

declare(strict_types=1);

namespace Pollora\BlockBinding\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\BlockBinding\Application\Services\BindingSourceRegistry;
use Pollora\BlockBinding\Infrastructure\Services\BindingEditorData;

/**
 * Lists the Pollora binding sources, what each one offers (fields, meta,
 * options), and the blocks whose attributes can be bound.
 */
#[Description('List the Block Bindings sources, their fields and the bindable blocks')]
#[Signature('pollora:binding:list {--json : Output as JSON}')]
class BindingListCommand extends Command
{
    public function handle(BindingSourceRegistry $sources, BindingEditorData $editorData): int
    {
        $offers = $editorData->toArray()['sources'];
        $list = [];

        foreach ($sources->all() as $source) {
            $list[] = [
                'name' => $source->name,
                'label' => $source->label,
                'class' => $source->class,
                'uses_context' => $source->usesContext,
                'post_types' => $source->postTypes,
                'offers' => $this->offers($offers[$source->name] ?? []),
            ];
        }

        $blocks = $this->bindableBlocks();

        if ($this->option('json')) {
            $this->line((string) json_encode(['sources' => $list, 'bindable_blocks' => $blocks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->components->info(sprintf('%d Pollora binding source(s)', count($list)));

        foreach ($list as $source) {
            $this->components->twoColumnDetail(
                sprintf('<fg=green;options=bold>%s</> %s', $source['name'], $source['label']),
                class_basename($source['class']).($source['post_types'] === [] ? '' : ' · '.implode(', ', $source['post_types']))
            );

            if ($source['offers'] === []) {
                $this->components->twoColumnDetail('  <fg=gray>nothing to offer yet</>');
            }

            foreach ($source['offers'] as $offer) {
                $this->components->twoColumnDetail('  '.$offer['args'], $offer['label'].($offer['for'] === '' ? '' : ' <fg=gray>('.$offer['for'].')</>'));
            }
        }

        if ($blocks !== null) {
            $this->newLine();
            $this->components->info(sprintf('%d bindable block(s)', count($blocks)));

            foreach ($blocks as $name => $attributes) {
                $this->components->twoColumnDetail($name, implode(', ', $attributes));
            }
        }

        return self::SUCCESS;
    }

    /**
     * What a source offers, flattened for display: one line per field, meta or option.
     *
     * @param  array<string, mixed>  $offer
     * @return list<array{args: string, label: string, for: string}>
     */
    private function offers(array $offer): array
    {
        $fields = $offer['fields'] ?? [];
        $groups = ($offer['kind'] ?? null) === 'meta' ? $fields : ['' => $fields];
        $lines = [];

        foreach ($groups as $subtype => $items) {
            foreach ($items as $item) {
                $line = [
                    'args' => implode(', ', array_map(static fn (string $name, string $value): string => sprintf('%s: %s', $name, $value), array_keys($item['args']), $item['args'])),
                    'label' => $item['label'],
                    'for' => (string) $subtype,
                ];

                // An attachment is offered twice (string and number): list it once
                if (! in_array($line, $lines, true)) {
                    $lines[] = $line;
                }
            }
        }

        return $lines;
    }

    /**
     * The registered blocks WordPress lets bind, with their attributes; null when WordPress is not loaded.
     *
     * @return array<string, list<string>>|null
     */
    protected function bindableBlocks(): ?array
    {
        if (! class_exists(\WP_Block_Type_Registry::class) || ! function_exists('get_block_bindings_supported_attributes')) {
            return null;
        }

        $blocks = [];

        foreach (array_keys(\WP_Block_Type_Registry::get_instance()->get_all_registered()) as $name) {
            $attributes = \get_block_bindings_supported_attributes($name);

            if ($attributes !== []) {
                $blocks[$name] = array_values($attributes);
            }
        }

        ksort($blocks);

        return $blocks;
    }
}
