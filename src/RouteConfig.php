<?php

declare(strict_types=1);

namespace Tivins\Webapp;

/**
 * Configuration d'une route API.
 *
 * Associe une classe contrôleur à une méthode HTTP pour le routage.
 */
readonly class RouteConfig
{
    /**
     * Crée une configuration de route.
     *
     * @param string $class Le nom complet de la classe contrôleur (doit implémenter RouteInterface)
     * @param HTTPMethod $method La méthode HTTP acceptée (GET, POST, PUT, DELETE, etc.)
     */
    public function __construct(
        public string     $pattern,
        public string     $class,
        public HTTPMethod $method = HTTPMethod::GET,
    )
    {
    }
}