<?php

namespace Tivins\Webapp;

use Exception;

/**
 * Exception levée lorsqu'un champ d'une entité Mappable est invalide.
 *
 * Contient des informations sur l'entité, le champ et le message d'erreur
 * pour faciliter le débogage et la validation.
 */
class InvalidFieldException extends Exception
{
    /**
     * Crée une nouvelle exception pour un champ invalide.
     *
     * @param Mappable $entity L'entité contenant le champ invalide
     * @param string $fieldName Le nom du champ invalide
     * @param string $errorMessage Le message d'erreur décrivant le problème
     *
     * @example
     * ```php
     * throw new InvalidFieldException(
     *     $user,
     *     'email',
     *     'L\'adresse email n\'est pas valide'
     * );
     * ```
     */
    public function __construct(
        public readonly Mappable $entity,
        public readonly string   $fieldName,
        public readonly string   $errorMessage,
    )
    {
        parent::__construct("Invalid field $fieldName: $errorMessage");
    }
}