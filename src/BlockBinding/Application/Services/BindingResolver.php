<?php

declare(strict_types=1);

namespace Pollora\BlockBinding\Application\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Support\Htmlable;
use Pollora\BlockBinding\Domain\Contracts\ContentVisibilityInterface;
use Pollora\BlockBinding\Domain\Contracts\ValuePresenterInterface;
use Pollora\BlockBinding\Domain\Enums\BindingFieldType;
use Pollora\BlockBinding\Domain\Models\BindingContext;
use Pollora\BlockBinding\Domain\Models\BindingFieldDefinition;
use Pollora\BlockBinding\Domain\Models\BindingSource;
use Pollora\Meta\Application\Services\MetaAccessor;
use Psr\Log\LoggerInterface;
use Stringable;
use Throwable;

/**
 * Answers WordPress for every bound attribute of every Pollora source.
 *
 * The checks live here, so that no source can forget them: the post or term of
 * the block must be visible to the visitor, and the value is escaped for the
 * place it lands in — text in HTML is escaped, a URL sanitized, an `HtmlString`
 * filtered like post content; an attribute a template prints (a Blade block's)
 * is left for the template to escape, like any other attribute.
 *
 * A field that throws reads as null (the block keeps its own content) and is
 * logged; in debug mode the exception is thrown, so it shows during development.
 */
final class BindingResolver
{
    /**
     * Attribute sources whose value WordPress writes as HTML.
     */
    private const array HTML_SOURCES = ['html', 'rich-text'];

    /**
     * @var array<string, mixed> Values already resolved in this request
     */
    private array $resolved = [];

    /**
     * @var array<class-string, object> Source instances, made on first use
     */
    private array $instances = [];

    public function __construct(
        private readonly Container $container,
        private readonly ContentVisibilityInterface $visibility,
        private readonly ValuePresenterInterface $presenter,
        private readonly ?MetaAccessor $metaAccessor = null,
        private readonly bool $debug = false,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * The value of a bound attribute, or null to keep what the block holds.
     *
     * @param  array<string, mixed>  $args  Arguments of the binding
     */
    public function resolve(BindingSource $source, array $args, ?\WP_Block $block, string $attribute): mixed
    {
        $context = $this->context($args, $block, $attribute);

        if (! $this->isVisible($context) || ! $source->answersFor($context->postType)) {
            return null;
        }

        $field = $source->isInvokable()
            ? new BindingFieldDefinition('', '', BindingFieldType::Text, '__invoke')
            : $source->field(is_string($args['field'] ?? null) ? $args['field'] : '');

        if (! $field instanceof BindingFieldDefinition) {
            return null;
        }

        $key = $this->cacheKey($source, $context);

        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        $value = $this->call($source, $field->method, $context);

        return $this->resolved[$key] = $this->present($value, $field->type, $block, $attribute);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function context(array $args, ?\WP_Block $block, string $attribute): BindingContext
    {
        $blockContext = $block instanceof \WP_Block ? $block->context : [];

        return new BindingContext(
            args: $args,
            attribute: $attribute,
            postId: $this->positiveInt($blockContext['postId'] ?? null),
            postType: is_string($blockContext['postType'] ?? null) ? $blockContext['postType'] : null,
            termId: $this->positiveInt($blockContext['termId'] ?? null),
            taxonomy: is_string($blockContext['taxonomy'] ?? null) ? $blockContext['taxonomy'] : null,
            block: $block,
            metaAccessor: $this->metaAccessor,
        );
    }

    private function isVisible(BindingContext $context): bool
    {
        if ($context->postId !== null && ! $this->visibility->canShowPost($context->postId)) {
            return false;
        }

        return $context->termId === null || $context->taxonomy === null || $this->visibility->canShowTerm($context->termId, $context->taxonomy);
    }

    private function call(BindingSource $source, string $method, BindingContext $context): mixed
    {
        try {
            $instance = $this->instances[$source->class] ??= $this->container->make($source->class);

            return $this->container->call([$instance, $method], [BindingContext::class => $context, 'context' => $context]);
        } catch (Throwable $throwable) {
            if ($this->debug) {
                throw $throwable;
            }

            $this->logger?->error(sprintf(
                'The block binding "%s" failed on %s: %s',
                $source->name,
                $context->postId === null ? 'no post' : 'post '.$context->postId,
                $throwable->getMessage()
            ), ['exception' => $throwable]);

            return null;
        }
    }

    private function present(mixed $value, BindingFieldType $type, ?\WP_Block $block, string $attribute): mixed
    {
        if ($value === null) {
            return null;
        }

        $attributeSource = $block?->block_type?->attributes[$attribute]['source'] ?? null;
        $inHtml = in_array($attributeSource, self::HTML_SOURCES, true);

        if ($value instanceof Htmlable) {
            return $this->presenter->richText($value->toHtml());
        }

        if (is_bool($value)) {
            return $inHtml ? $this->presenter->html($this->presenter->boolean($value)) : $value;
        }

        if ((is_int($value) || is_float($value)) && ! $inHtml && ! $type->isUrl()) {
            return $value;
        }

        $string = $value instanceof Stringable || is_scalar($value) ? (string) $value : '';

        return match (true) {
            $type->isUrl() => $this->presenter->url($string),
            $inHtml => $this->presenter->html($string),
            default => $string,
        };
    }

    private function cacheKey(BindingSource $source, BindingContext $context): string
    {
        return implode('|', [
            $source->name,
            $context->attribute,
            (string) $context->postId,
            (string) $context->termId,
            (string) $context->block?->name,
            serialize($context->args),
        ]);
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
