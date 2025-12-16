<?php

declare(strict_types=1);

namespace Pollora\Logging\Infrastructure\Services;

use Pollora\Logging\Domain\Contracts\LoggerInterface;
use Psr\Log\LoggerTrait;
use Stringable;

/**
 * Implémentation fallback utilisant error_log natif PHP.
 *
 * Utilisé quand le container Laravel n'est pas disponible
 * ou en cas de problème avec le système de logging Laravel.
 *
 * @internal
 */
final readonly class FallbackLogger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * Nom du channel pour identifier le fallback.
     */
    private const CHANNEL_NAME = 'error_log';

    /**
     * @param  bool  $debugEnabled  Indique si le mode debug est activé
     */
    public function __construct(
        private bool $debugEnabled = false,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $formattedMessage = $this->format($level, $message, $context);
        error_log($formattedMessage);
    }

    /**
     * {@inheritDoc}
     */
    public function logWithModule(string $level, string $message, array $context = []): void
    {
        $context['pollora_framework'] = true;
        $this->log($level, $message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function getChannelName(): string
    {
        return self::CHANNEL_NAME;
    }

    /**
     * {@inheritDoc}
     */
    public function isDebugEnabled(): bool
    {
        return $this->debugEnabled;
    }

    /**
     * Formate le message pour error_log avec niveau et contexte.
     *
     * @param  string  $level  Le niveau de log PSR-3
     * @param  string|Stringable  $message  Le message à logger
     * @param  array  $context  Le contexte additionnel
     * @return string Le message formaté pour error_log
     */
    private function format(string $level, string|Stringable $message, array $context): string
    {
        $levelUpper = strtoupper($level);
        $contextString = $context !== [] ? ' '.json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        $timestamp = date('Y-m-d H:i:s');

        return sprintf('[%s] [Pollora] [%s] %s%s', $timestamp, $levelUpper, $message, $contextString);
    }
}
