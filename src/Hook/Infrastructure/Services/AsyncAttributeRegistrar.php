<?php

declare(strict_types=1);

namespace Pollora\Hook\Infrastructure\Services;

use Pollora\Attributes\Action;
use Pollora\Attributes\Async;
use Pollora\Hook\Application\Services\AsyncDeclarationFailures;
use Pollora\Hook\Async\PendingAsync;
use Pollora\Hook\Domain\Contract\Action as ActionContract;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * Registers an #[Action] method, asynchronously when it or its class carries #[Async].
 *
 * A declaration that cannot be honoured (an unknown hook in except, a capture
 * or when method that is not a public method of the class, invalid attempts,
 * backoff or lock duration) is logged, kept for pollora:doctor, and the
 * action is registered synchronously: the work still happens.
 */
final readonly class AsyncAttributeRegistrar
{
    public function __construct(
        private ActionContract $actions,
        private ?LoggerInterface $logger = null,
        private ?AsyncDeclarationFailures $failures = null,
    ) {}

    /**
     * The #[Async] of a method, or else of its class.
     */
    public static function attributeFor(ReflectionMethod $method): ?Async
    {
        $attributes = $method->getAttributes(Async::class) ?: $method->getDeclaringClass()->getAttributes(Async::class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    /**
     * Whether the method itself carries #[Async].
     */
    public static function isDeclaredOn(ReflectionMethod $method): bool
    {
        return $method->getAttributes(Async::class) !== [];
    }

    /**
     * Register one hook of an #[Action] method.
     *
     * @param  int|null  $acceptedArgs  Null to detect it from the method
     */
    public function register(string $hook, object $instance, ReflectionMethod $method, int $priority, ?int $acceptedArgs = null): void
    {
        $this->actions->add($hook, [$instance, $method->getName()], $priority, $acceptedArgs);

        $attribute = self::attributeFor($method);

        if (! $attribute instanceof Async) {
            return;
        }

        $problem = $this->problem($method, $attribute);

        if ($problem !== null) {
            $this->logger?->error(sprintf('#[Async] on %s::%s() is ignored, the action runs synchronously: %s', $method->getDeclaringClass()->getName(), $method->getName(), $problem));
            $this->failures?->fail($method->getDeclaringClass()->getName(), $method->getName(), $problem);

            return;
        }

        if (in_array($hook, (array) $attribute->except, true)) {
            return;
        }

        $this->configure($this->actions->async(), $attribute, $instance);
    }

    /**
     * Why a declaration cannot be honoured, or null.
     */
    private function problem(ReflectionMethod $method, Async $attribute): ?string
    {
        $declaredOnMethod = self::isDeclaredOn($method);
        $hooks = $this->actionHooks($method, $declaredOnMethod);

        $unknown = array_values(array_diff((array) $attribute->except, $hooks));
        if ($unknown !== []) {
            return sprintf("except names '%s', which is not a hook of its #[Action] (%s)", implode("', '", $unknown), implode(', ', $hooks));
        }

        foreach (['capture' => $attribute->capture, 'when' => $attribute->when] as $option => $name) {
            if ($name !== null && ! $this->isPublicMethod($method->getDeclaringClass(), $name)) {
                return sprintf("%s names '%s', which is not a public method of the class", $option, $name);
            }
        }

        try {
            if ($attribute->tries !== null) {
                PendingAsync::validTries($attribute->tries);
            }

            if ($attribute->backoff !== null) {
                PendingAsync::validBackoff($attribute->backoff);
            }
        } catch (\InvalidArgumentException $invalidArgumentException) {
            return $invalidArgumentException->getMessage();
        }

        if (is_int($attribute->unique) && $attribute->unique < 1) {
            return sprintf('unique expects true or a lock of at least 1 second, %d given', $attribute->unique);
        }

        return null;
    }

    /**
     * Hooks of the #[Action] attributes of the method, or of the whole class for a class-level #[Async].
     *
     * @return list<string>
     */
    private function actionHooks(ReflectionMethod $method, bool $declaredOnMethod): array
    {
        $methods = $declaredOnMethod ? [$method] : $method->getDeclaringClass()->getMethods(ReflectionMethod::IS_PUBLIC);
        $hooks = [];

        foreach ($methods as $candidate) {
            foreach ($candidate->getAttributes(Action::class) as $action) {
                $hooks[] = $action->newInstance()->hook;
            }
        }

        return array_values(array_unique($hooks));
    }

    /**
     * @param  \ReflectionClass<object>  $class
     */
    private function isPublicMethod(\ReflectionClass $class, string $name): bool
    {
        return $class->hasMethod($name) && $class->getMethod($name)->isPublic();
    }

    private function configure(PendingAsync $pending, Async $attribute, object $instance): void
    {
        if ($attribute->delay !== null) {
            $pending->delay($attribute->delay);
        }

        if ($attribute->via !== null) {
            $pending->via($attribute->via);
        }

        if ($attribute->onQueue !== null) {
            $pending->onQueue($attribute->onQueue);
        }

        if ($attribute->unique === true) {
            $pending->unique();
        } elseif (is_int($attribute->unique)) {
            $pending->unique($attribute->unique);
        }

        if ($attribute->tries !== null) {
            $pending->tries($attribute->tries);
        }

        if ($attribute->backoff !== null) {
            $pending->backoff($attribute->backoff);
        }

        if ($attribute->asUser !== null) {
            $pending->asUser($attribute->asUser);
        }

        if ($attribute->capture !== null) {
            $pending->capture([$instance, $attribute->capture]);
        }

        if ($attribute->when !== null) {
            $pending->when([$instance, $attribute->when]);
        }

        if ($attribute->keepMissing) {
            $pending->keepMissing();
        }
    }
}
