<?php

declare(strict_types=1);

namespace Pollora\Meta\Infrastructure\Checks;

use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Meta\Application\Services\MetaAuditor;
use Pollora\Meta\Application\Services\MetaSchemaRepository;
use Pollora\Meta\Domain\Enums\MetaObjectType;
use Pollora\Meta\Domain\Models\MetaSchema;

/**
 * Every `#[Meta]` declaration is registered and reachable, and its stored
 * values read as their type.
 *
 * Each failure below is only logged, or not even that: a declaration refused
 * at discovery leaves its meta unregistered; a typo in `#[PostMeta('prodcut')]`
 * registers meta on a post type that does not exist; a meta in REST on a post
 * type that is not leaves it out of every response; a stored value of the
 * wrong type reads as the default.
 */
final readonly class TypedMetaCheck implements CheckInterface
{
    /** Rows read per meta key: enough to notice a problem, cheap enough for Site Health. */
    private const int SAMPLE_ROWS = 200;

    public function __construct(
        private MetaSchemaRepository $schemas,
        private MetaAuditor $auditor,
    ) {}

    public function id(): string
    {
        return 'typed-meta';
    }

    public function label(): string
    {
        return 'Typed meta';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        if (! function_exists('post_type_exists') || \did_action('init') === 0) {
            return CheckResult::skipped('WordPress is not loaded.');
        }

        $schemas = $this->schemas->all();
        $failures = $this->schemas->failures();

        if ($schemas === [] && $failures === []) {
            return CheckResult::ok('No #[Meta] declared.');
        }

        $warnings = [...$this->unreachable($schemas), ...$this->unreadable()];

        if ($failures !== []) {
            return CheckResult::error(
                sprintf('%d class(es) declare meta WordPress never registers.', count($failures)),
                [...array_map(static fn (string $class, string $reason): string => $class.': '.$reason, array_keys($failures), $failures), ...$warnings],
                'Fix the declaration named in each line, then run php artisan discovery:clear',
            );
        }

        if ($warnings !== []) {
            return CheckResult::warning(
                sprintf('%d problem(s) with typed meta.', count($warnings)),
                $warnings,
                'php artisan pollora:meta:audit reads every stored value and lists the keys nothing declares',
            );
        }

        $count = array_sum(array_map(static fn (MetaSchema $schema): int => count($schema->definitions), $schemas));

        return CheckResult::ok(sprintf('%d meta declared by %d class(es), registered and readable.', $count, count($schemas)));
    }

    /**
     * Meta registered on a post type or taxonomy that does not exist, or kept
     * out of REST by an object type that is not in REST.
     *
     * @param  list<MetaSchema>  $schemas
     * @return list<string>
     */
    private function unreachable(array $schemas): array
    {
        $warnings = [];

        foreach ($schemas as $schema) {
            if (! in_array($schema->objectType, [MetaObjectType::Post, MetaObjectType::Term], true)) {
                continue;
            }

            foreach ($schema->subtypes as $subtype) {
                $object = $schema->objectType === MetaObjectType::Post ? \get_post_type_object($subtype) : \get_taxonomy($subtype);

                if (! $object instanceof \WP_Post_Type && ! $object instanceof \WP_Taxonomy) {
                    $warnings[] = sprintf('%s: the %s "%s" does not exist, so its meta are never used', $schema->declaringClass, $schema->objectType === MetaObjectType::Post ? 'post type' : 'taxonomy', $subtype);
                } elseif ($schema->exposesInRest() && ! $object->show_in_rest) {
                    $warnings[] = sprintf('%s: meta marked showInRest are not in the REST API, because %s "%s" is not (show_in_rest)', $schema->declaringClass, $schema->objectType === MetaObjectType::Post ? 'the post type' : 'the taxonomy', $subtype);
                }
            }
        }

        return $warnings;
    }

    /**
     * @return list<string>
     */
    private function unreadable(): array
    {
        return array_map(
            static fn (array $finding): string => sprintf('%s::$%s (%s): %d stored value(s) read as the default — %s', $finding['class'], $finding['property'], $finding['owner'], $finding['unreadable'], $finding['example']),
            $this->auditor->unreadable(self::SAMPLE_ROWS),
        );
    }
}
