<?php

declare(strict_types=1);

namespace Pollora\Logging\Domain\Enums;

use Psr\Log\LogLevel as PsrLogLevel;

/**
 * Énumération des niveaux de log avec mapping PSR-3.
 *
 * Fournit une interface type-safe pour les niveaux de logging
 * tout en maintenant la compatibilité avec PSR-3.
 *
 * @api
 */
enum LogLevel: string
{
    case EMERGENCY = PsrLogLevel::EMERGENCY;
    case ALERT = PsrLogLevel::ALERT;
    case CRITICAL = PsrLogLevel::CRITICAL;
    case ERROR = PsrLogLevel::ERROR;
    case WARNING = PsrLogLevel::WARNING;
    case NOTICE = PsrLogLevel::NOTICE;
    case INFO = PsrLogLevel::INFO;
    case DEBUG = PsrLogLevel::DEBUG;

    /**
     * Retourne la priorité numérique (pour filtrage).
     *
     * Plus la valeur est élevée, plus le niveau est critique.
     *
     * @return int La priorité numérique du niveau
     */
    public function priority(): int
    {
        return match ($this) {
            self::EMERGENCY => 800,
            self::ALERT => 700,
            self::CRITICAL => 600,
            self::ERROR => 500,
            self::WARNING => 400,
            self::NOTICE => 300,
            self::INFO => 200,
            self::DEBUG => 100,
        };
    }

    /**
     * Vérifie si ce niveau est plus critique qu'un autre.
     *
     * @param  LogLevel  $other  Le niveau à comparer
     * @return bool True si ce niveau est plus critique
     */
    public function isMoreCriticalThan(LogLevel $other): bool
    {
        return $this->priority() > $other->priority();
    }

    /**
     * Retourne tous les niveaux ordonnés par criticité décroissante.
     *
     * @return array<LogLevel>
     */
    public static function allByPriority(): array
    {
        return [
            self::EMERGENCY,
            self::ALERT,
            self::CRITICAL,
            self::ERROR,
            self::WARNING,
            self::NOTICE,
            self::INFO,
            self::DEBUG,
        ];
    }
}
