<?php

declare(strict_types=1);

namespace Tivins\Webapp;

use Closure;
use InvalidArgumentException;

/**
 * Configuration d'une route API.
 *
 * Associe une classe contrôleur ou un callable à une méthode HTTP pour le routage.
 */
readonly class RouteConfig
{
    /**
     * Crée une configuration de route.
     *
     * @param string $pattern Le pattern de la route (expression régulière)
     * @param string|Closure|array $handler Le nom complet de la classe contrôleur (doit implémenter RouteInterface)
     *                                        ou un callable de la forme `fn(Request $request, array $matches): HTTPResponse`
     *                                        ou un tableau `[Class::class, 'method']` ou `[$object, 'method']`
     * @param HTTPMethod $method La méthode HTTP acceptée (GET, POST, PUT, DELETE, etc.)
     * @throws InvalidArgumentException Si $handler n'est ni une string, ni un callable valide
     */
    public function __construct(
        public string               $pattern,
        public string|Closure|array $handler,
        public HTTPMethod           $method = HTTPMethod::GET,
    )
    {
        // Validation : si c'est un array, vérifier qu'il est callable
        if (is_array($handler) && !is_callable($handler)) {
            throw new InvalidArgumentException('Handler array must be callable');
        }
    }
}