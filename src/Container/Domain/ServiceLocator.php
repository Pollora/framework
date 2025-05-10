<?php

declare(strict_types=1);

namespace Pollora\Container\Domain;

/**
 * Interface ServiceLocator
 *
 * Définit un contrat pour résoudre des services à partir d'un conteneur.
 * Cette interface fait partie du domaine et est indépendante de l'implémentation spécifique.
 */
interface ServiceLocator
{
    /**
     * Résout un service à partir de sa classe.
     *
     * @param string $serviceClass La classe du service à résoudre
     * @return mixed|null Le service résolu ou null s'il n'est pas trouvé
     */
    public function resolve(string $serviceClass);
}
