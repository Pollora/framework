<?php

declare(strict_types=1);

namespace Pollora\Logging\Infrastructure\Factories;

use Illuminate\Contracts\Container\Container;
use Illuminate\Log\LogManager;
use Pollora\Logging\Domain\Contracts\LoggerInterface;
use Pollora\Logging\Infrastructure\Services\FallbackLogger;
use Pollora\Logging\Infrastructure\Services\LaravelLogger;

/**
 * Factory pour la création contextuelle du logger approprié.
 *
 * Résout automatiquement le bon logger selon la disponibilité
 * du container Laravel et du LogManager.
 *
 * @internal
 */
final readonly class LoggerFactory
{
    /**
     * @param  Container|null  $container  Instance du container Laravel (optionnelle)
     */
    public function __construct(
        private ?Container $container = null,
    ) {}

    /**
     * Crée l'instance de logger appropriée selon le contexte.
     *
     * Utilise LaravelLogger si Laravel est disponible,
     * sinon fallback vers FallbackLogger avec error_log.
     *
     * @return LoggerInterface L'instance de logger créée
     */
    public function create(): LoggerInterface
    {
        // Si le container Laravel est disponible avec le LogManager
        if ($this->isLaravelAvailable()) {
            return new LaravelLogger(
                logManager: $this->container->make(LogManager::class),
                debugEnabled: $this->isDebugEnabled(),
            );
        }

        // Fallback vers error_log standard
        return new FallbackLogger(
            debugEnabled: $this->isDebugEnabled(),
        );
    }

    /**
     * Vérifie si Laravel est disponible et configuré.
     *
     * @return bool True si Laravel est disponible
     */
    private function isLaravelAvailable(): bool
    {
        if (! $this->container instanceof \Illuminate\Contracts\Container\Container) {
            return false;
        }

        return $this->container->bound(LogManager::class);
    }

    /**
     * Détermine si le mode debug est activé.
     *
     * Vérifie la configuration Laravel ou utilise une détection
     * basée sur les variables d'environnement.
     *
     * @return bool True si le debug est activé
     */
    private function isDebugEnabled(): bool
    {
        if (! $this->container instanceof \Illuminate\Contracts\Container\Container) {
            // Fallback: vérification directe des variables d'environnement
            return $this->getEnvDebugStatus();
        }

        try {
            return (bool) $this->container->make('config')->get('app.debug', false);
        } catch (\Throwable) {
            // En cas d'erreur, utiliser la détection d'environnement
            return $this->getEnvDebugStatus();
        }
    }

    /**
     * Détecte le statut debug depuis les variables d'environnement.
     *
     * @return bool True si le debug est activé
     */
    private function getEnvDebugStatus(): bool
    {
        $debug = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? 'false';

        return filter_var($debug, FILTER_VALIDATE_BOOLEAN);
    }
}
