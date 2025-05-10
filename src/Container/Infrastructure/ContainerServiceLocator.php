<?php

declare(strict_types=1);

namespace Pollora\Container\Infrastructure;

use Pollora\Container\Domain\ServiceLocator;

/**
 * Class ContainerServiceLocator
 *
 * Implémentation du service locator qui utilise un conteneur d'injection de dépendances.
 * Cette classe fait partie de l'infrastructure et s'occupe de la résolution concrète des services.
 */
class ContainerServiceLocator implements ServiceLocator
{
    /**
     * @var mixed Le conteneur d'injection de dépendances
     */
    private $container;

    /**
     * ContainerServiceLocator constructor.
     *
     * @param mixed $container Le conteneur d'injection de dépendances
     */
    public function __construct($container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(string $serviceClass)
    {
        if (!$this->container) {
            return null;
        }

        // Gestion des conteneurs de type tableau associatif
        if (is_array($this->container) && isset($this->container[$serviceClass])) {
            return $this->container[$serviceClass];
        }

        // Gestion des conteneurs de type PSR-11 ou similaires
        if (is_object($this->container) && method_exists($this->container, 'get')) {
            try {
                return $this->container->get($serviceClass);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }
}
