<?php

declare(strict_types=1);

namespace Pollora\Logging\Infrastructure\Services;

use Illuminate\Log\LogManager;
use Pollora\Logging\Domain\Contracts\LoggerInterface;
use Psr\Log\LoggerTrait;
use Stringable;

/**
 * Implémentation du logger utilisant le système Laravel.
 *
 * Utilise le channel "pollora" configuré dans config/logging.php
 * pour diriger les logs du framework vers un fichier dédié.
 *
 * @internal
 */
final readonly class LaravelLogger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * Nom du channel de logging dédié à Pollora.
     */
    private const CHANNEL_NAME = 'pollora';

    /**
     * @param  LogManager  $logManager  Instance du gestionnaire de logs Laravel
     * @param  bool  $debugEnabled  Indique si le mode debug est activé
     */
    public function __construct(
        private LogManager $logManager,
        private bool $debugEnabled = false,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->logManager
            ->channel(self::CHANNEL_NAME)
            ->log($level, $this->formatMessage($message), $this->enrichContext($context));
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
     * Formate le message avec le préfixe Pollora.
     *
     * @param  string|Stringable  $message  Le message à formater
     * @return string Le message formaté
     */
    private function formatMessage(string|Stringable $message): string
    {
        return '[Pollora] '.$message;
    }

    /**
     * Enrichit le contexte avec des métadonnées framework.
     *
     * @param  array  $context  Le contexte original
     * @return array Le contexte enrichi
     */
    private function enrichContext(array $context): array
    {
        return array_merge([
            'framework' => 'pollora',
            'timestamp' => now()->toIso8601String(),
        ], $context);
    }
}
