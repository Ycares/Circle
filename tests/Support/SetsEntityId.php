<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * Les entités du Domain n'exposent pas de setter pour leur id (assigné par Doctrine via
 * réflexion en production). Ce trait reproduit ce mécanisme pour les tests unitaires qui ont
 * besoin d'entités avec un id défini (ex. vérifications de propriété/appartenance).
 */
trait SetsEntityId
{
    private function setEntityId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }
}
