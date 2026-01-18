<?php

declare(strict_types=1);

namespace Tivins\Webapp;

use Attribute;

/**
 * Attribut PHP pour définir les métadonnées d'une route API.
 *
 * Cet attribut peut être appliqué sur :
 * - Une classe (pour définir des métadonnées par défaut pour toutes les méthodes)
 * - La méthode `trigger()` d'un RouteInterface
 * - Toute méthode utilisée comme handler de route
 *
 * Les métadonnées définies via cet attribut ont priorité sur les PHPDoc
 * lors de la génération de la documentation OpenAPI.
 *
 * Lorsqu'un attribut est présent sur la classe et sur la méthode, les valeurs
 * de la méthode surchargent celles de la classe (fusion des tableaux pour les tags).
 *
 * @example
 * ```php
 * #[RouteAttribute(tags: ['Users'])]
 * class UserController implements RouteInterface {
 *     #[RouteAttribute(
 *         name: 'Get user by ID',
 *         description: 'Retrieves a user by their unique identifier',
 *         operationId: 'getUserById'
 *     )]
 *     public function trigger(Request $request, array $matches): HTTPResponse {
 *         // Cette méthode héritera du tag 'Users' de la classe
 *         // ...
 *     }
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class RouteAttribute
{
    /**
     * @param string $name Le nom/résumé court de l'opération (summary dans OpenAPI)
     * @param string $description Description détaillée de l'opération
     * @param ContentType $contentType Le type de contenu de la réponse
     * @param array<string> $tags Tags pour grouper les opérations dans OpenAPI
     * @param bool $deprecated Indique si l'opération est dépréciée
     * @param string $operationId Identifiant unique de l'opération (auto-généré si vide)
     */
    public function __construct(
        public string $name = '',
        public string $description = '',
        public ContentType $contentType = ContentType::JSON,
        public array $tags = [],
        public bool $deprecated = false,
        public string $operationId = '',
    ) { }
}