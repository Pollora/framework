<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Domain\Contracts;

use Illuminate\Support\Collection;

/**
 * Interface pour les scouts qui peuvent traiter les classes découvertes.
 *
 * Cette interface étend le concept de base des scouts en permettant
 * de définir un traitement spécifique pour les classes découvertes.
 */
interface HandlerScoutInterface
{
    /**
     * Traite les classes découvertes.
     *
     * Cette méthode est appelée automatiquement après la découverte
     * des classes et permet d'implémenter la logique de traitement
     * spécifique à chaque type de scout.
     *
     * @return void
     *
     * @throws \Throwable En cas d'erreur lors du traitement
     */
    public function handle(): void;
}
