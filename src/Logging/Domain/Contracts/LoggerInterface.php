<?php

declare(strict_types=1);

namespace Pollora\Logging\Domain\Contracts;

use Psr\Log\LoggerInterface as PsrLoggerInterface;

/**
 * Interface de logging pour le framework Pollora.
 *
 * Étend PSR-3 avec des méthodes spécifiques au framework.
 *
 * @api
 */
interface LoggerInterface extends PsrLoggerInterface
{
    /**
     * Log avec préfixe automatique du module Pollora.
     *
     * @param  string  $level  Niveau de log PSR-3
     * @param  string  $message  Message à logger
     * @param  array  $context  Contexte additionnel
     */
    public function logWithModule(string $level, string $message, array $context = []): void;

    /**
     * Retourne le nom du channel utilisé.
     */
    public function getChannelName(): string;

    /**
     * Vérifie si le logger est en mode debug.
     */
    public function isDebugEnabled(): bool;
}
