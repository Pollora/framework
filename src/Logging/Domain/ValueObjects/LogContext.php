<?php

declare(strict_types=1);

namespace Pollora\Logging\Domain\ValueObjects;

use Throwable;

/**
 * Value Object encapsulant le contexte d'un log.
 *
 * Fournit une structure typée pour le contexte des logs
 * avec des métadonnées spécifiques au framework Pollora.
 *
 * @api
 */
final readonly class LogContext
{
    /**
     * @param  string  $module  Module Pollora source (ex: "Hook", "PostType")
     * @param  string|null  $class  Classe source
     * @param  string|null  $method  Méthode source
     * @param  array  $extra  Données additionnelles
     * @param  Throwable|null  $exception  Exception associée
     */
    public function __construct(
        public string $module,
        public ?string $class = null,
        public ?string $method = null,
        public array $extra = [],
        public ?Throwable $exception = null,
    ) {}

    /**
     * Convertit en tableau pour le contexte PSR-3.
     *
     * @return array<string, mixed> Le contexte sous forme de tableau
     */
    public function toArray(): array
    {
        $context = [
            'pollora_module' => $this->module,
        ];

        if ($this->class !== null) {
            $context['class'] = $this->class;
        }

        if ($this->method !== null) {
            $context['method'] = $this->method;
        }

        if ($this->exception instanceof \Throwable) {
            $context['exception'] = $this->exception;
        }

        return array_merge($context, $this->extra);
    }

    /**
     * Factory method pour création depuis une classe.
     *
     * Extrait automatiquement le module depuis le namespace de la classe.
     *
     * @param  string  $className  Le nom complet de la classe
     * @param  string|null  $method  Le nom de la méthode (optionnel)
     * @param  array  $extra  Données additionnelles (optionnel)
     * @return self L'instance LogContext créée
     */
    public static function fromClass(string $className, ?string $method = null, array $extra = []): self
    {
        $parts = explode('\\', $className);

        // Détection du module depuis le namespace (ex: Pollora\Hook\...)
        $module = 'Core';
        if (count($parts) >= 2 && $parts[0] === 'Pollora') {
            $module = $parts[1];
        }

        return new self(
            module: $module,
            class: $className,
            method: $method,
            extra: $extra,
        );
    }

    /**
     * Factory method pour création avec exception.
     *
     * @param  string  $module  Le module source
     * @param  Throwable  $exception  L'exception à associer
     * @param  array  $extra  Données additionnelles (optionnel)
     * @return self L'instance LogContext créée
     */
    public static function fromException(string $module, Throwable $exception, array $extra = []): self
    {
        return new self(
            module: $module,
            extra: $extra,
            exception: $exception,
        );
    }

    /**
     * Ajoute des données supplémentaires au contexte.
     *
     * @param  array  $additionalData  Données à ajouter
     * @return self Nouvelle instance avec les données ajoutées
     */
    public function withExtra(array $additionalData): self
    {
        return new self(
            module: $this->module,
            class: $this->class,
            method: $this->method,
            extra: array_merge($this->extra, $additionalData),
            exception: $this->exception,
        );
    }

    /**
     * Ajoute une exception au contexte.
     *
     * @param  Throwable  $exception  L'exception à associer
     * @return self Nouvelle instance avec l'exception
     */
    public function withException(Throwable $exception): self
    {
        return new self(
            module: $this->module,
            class: $this->class,
            method: $this->method,
            extra: $this->extra,
            exception: $exception,
        );
    }
}
