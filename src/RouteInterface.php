<?php

declare(strict_types=1);

namespace Tivins\Webapp;

/**
 * Interface que doivent implémenter tous les contrôleurs de routes.
 *
 * Les classes qui implémentent cette interface peuvent être utilisées
 * comme contrôleurs dans le système de routage de l'API.
 */
interface RouteInterface
{
    /**
     * Traite une requête HTTP et retourne une réponse.
     *
     * Cette méthode est appelée automatiquement par le système de routage
     * lorsqu'une route correspond à la requête.
     *
     * @param Request $request La requête HTTP à traiter
     * @param array $matches Les captures des groupes de l'expression régulière de la route
     * @return HTTPResponse La réponse HTTP à envoyer au client
     *
     * @example
     * ```php
     * class UserController implements RouteInterface {
     *     public function trigger(Request $request, array $matches): HTTPResponse {
     *         $userId = $matches[0]; // Premier groupe capturé
     *         $user = $this->userRegistry->find($userId);
     *         return new HTTPResponse(200, $user);
     *     }
     * }
     * ```
     */
    public function trigger(Request $request, array $matches): HTTPResponse;
}