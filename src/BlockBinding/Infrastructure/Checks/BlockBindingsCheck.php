<?php

declare(strict_types=1);

namespace Pollora\BlockBinding\Infrastructure\Checks;

use Pollora\BlockBinding\Application\Services\BindingReferenceInspector;
use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Doctor\Infrastructure\Support\ProjectModule;
use Pollora\Doctor\Infrastructure\Support\ProjectModules;

/**
 * Every Block Binding written in the templates, template parts and patterns of
 * the theme, the Pollora plugins and the modules can show its value, and every
 * block.json listing `pollora.bindings` can be bound.
 *
 * A binding to a source that does not exist, to a field the source does not
 * have, to an undeclared meta or to an attribute the block cannot bind leaves
 * the block with its saved content: no error, no notice, a block that never
 * changes. Content saved in the database is not read.
 */
final readonly class BlockBindingsCheck implements CheckInterface
{
    /** Where WordPress reads block markup, by extension. */
    private const array MARKUP = ['templates' => ['html'], 'parts' => ['html'], 'patterns' => ['php', 'html']];

    /** Where Pollora looks for blocks, the legacy location included until v15. */
    private const array BLOCKS = ['resources/views/blocks', 'resources/blocks'];

    public function __construct(
        private ProjectModules $modules,
        private BindingReferenceInspector $inspector,
    ) {}

    public function id(): string
    {
        return 'block-bindings';
    }

    public function label(): string
    {
        return 'Block bindings';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        if (! function_exists('parse_blocks') || ! function_exists('get_block_bindings_source') || \did_action('init') === 0) {
            return CheckResult::skipped('WordPress is not loaded.');
        }

        $errors = [];
        $warnings = [];
        $bindings = 0;

        foreach ($this->modules->all() as $module) {
            foreach (self::MARKUP as $directory => $extensions) {
                foreach ($module->files($directory, $extensions) as $file) {
                    $blocks = \parse_blocks((string) file_get_contents($module->root.'/'.$file));
                    $bindings += $this->inspectBlocks($blocks, sprintf('%s: %s', $module->label(), $file), $errors, $warnings);
                }
            }

            $this->inspectBlockTypes($module, $errors);
        }

        if ($errors !== []) {
            return CheckResult::error(
                sprintf('%d binding(s) can never show a value: the blocks keep their saved content.', count($errors)),
                [...$errors, ...$warnings],
                'Fix the source, field, key or attribute named in each line; php artisan pollora:binding:list shows what each Pollora source offers',
            );
        }

        if ($warnings !== []) {
            return CheckResult::warning(
                sprintf('%d binding(s) are ignored by WordPress.', count($warnings)),
                $warnings,
                'Bind only the attributes WordPress supports for that block, or list them under "pollora": {"bindings": [...]} in its block.json',
            );
        }

        return CheckResult::ok($bindings === 0 ? 'No block binding in the templates, parts and patterns.' : sprintf('%d block binding(s), each able to show its value.', $bindings));
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    private function inspectBlocks(array $blocks, string $where, array &$errors, array &$warnings): int
    {
        $count = 0;

        foreach ($blocks as $block) {
            $name = is_string($block['blockName'] ?? null) ? $block['blockName'] : null;
            $bindings = $block['attrs']['metadata']['bindings'] ?? null;

            if ($name !== null && is_array($bindings)) {
                $supported = \get_block_bindings_supported_attributes($name);

                foreach ($bindings as $attribute => $binding) {
                    $sourceName = is_array($binding) && is_string($binding['source'] ?? null) ? $binding['source'] : null;

                    // Pattern overrides bind every attribute through "__default"
                    if ($attribute === '__default' || $sourceName === 'core/pattern-overrides') {
                        continue;
                    }

                    $count++;
                    $line = sprintf('%s — %s, "%s"', $where, $name, $attribute);

                    if ($sourceName === null || \get_block_bindings_source($sourceName) === null) {
                        $errors[] = sprintf('%s: the source "%s" is not registered', $line, $sourceName ?? '');

                        continue;
                    }

                    $problem = $this->inspector->problem($sourceName, is_array($binding['args'] ?? null) ? $binding['args'] : []);

                    if ($problem !== null) {
                        $errors[] = sprintf('%s → %s: %s', $line, $sourceName, $problem);
                    } elseif (! in_array($attribute, $supported, true)) {
                        $warnings[] = sprintf('%s: WordPress does not bind this attribute of %s', $line, $name);
                    }
                }
            }

            if (is_array($block['innerBlocks'] ?? null) && $block['innerBlocks'] !== []) {
                $count += $this->inspectBlocks($block['innerBlocks'], $where, $errors, $warnings);
            }
        }

        return $count;
    }

    /**
     * A block.json listing pollora.bindings that WordPress cannot honour.
     *
     * @param  list<string>  $errors
     */
    private function inspectBlockTypes(ProjectModule $module, array &$errors): void
    {
        foreach (self::BLOCKS as $directory) {
            foreach ($module->files($directory, ['json']) as $file) {
                if (basename($file) !== 'block.json') {
                    continue;
                }

                $metadata = json_decode((string) file_get_contents($module->root.'/'.$file), true);
                $listed = is_array($metadata) ? ($metadata['pollora']['bindings'] ?? null) : null;

                if (! is_array($listed)) {
                    continue;
                }

                $where = sprintf('%s: %s', $module->label(), $file);

                if (! isset($metadata['render'])) {
                    $errors[] = $where.': lists pollora.bindings but has no "render" — only a block rendered on the server can be bound';

                    continue;
                }

                $declared = is_array($metadata['attributes'] ?? null) ? $metadata['attributes'] : [];

                foreach ($listed as $attribute) {
                    if (! is_string($attribute) || ! array_key_exists($attribute, $declared)) {
                        $errors[] = sprintf('%s: pollora.bindings lists "%s", which is not one of its attributes', $where, is_scalar($attribute) ? (string) $attribute : get_debug_type($attribute));
                    }
                }
            }
        }
    }
}
