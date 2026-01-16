<?php

declare(strict_types=1);

namespace Tivins\Webapp;

class API
{
    /**
     * Crée une instance de l'API avec un chemin de base optionnel.
     *
     * @param string $basePath Chemin de base pour toutes les routes (ex: '/api/v1')
     *
     * @example
     * ```php
     * $api = new API('/api/v1');
     * ```
     */
    public function __construct(public readonly string $basePath = '')
    {
    }

    /**
     * Définit les routes de l'API.
     *
     * @param array<string, RouteMenu> $routes Tableau associatif où les clés sont des expressions régulières
     *                                         et les valeurs sont des objets RouteMenu
     * @return static Instance courante pour le chaînage de méthodes
     *
     * @example
     * ```php
     * $api->setRoutes([
     *     '/users/(\d+)' => new RouteMenu(UserController::class, HTTPMethod::GET),
     *     '/users' => new RouteMenu(UsersController::class, HTTPMethod::POST),
     * ]);
     * ```
     */
    public function setRoutes(array $routes): static
    {
        foreach ($routes as $route) {
            $this->routesByMethod[$route->method->value][] = [
                'pattern' => "~^$this->basePath$route->pattern$~",
                'class' => $route->class,
                'regex' => $route->pattern
            ];
        }
        return $this;
    }

    public function get(RouteMenu $route): static
    {
        $this->routesByMethod[HTTPMethod::GET->value][] = [
            'pattern' => "~^$this->basePath$route->pattern$~",
            'class' => $route->class,
            'regex' => $route->pattern
        ];
        return $this;
    }

    public function post(RouteMenu $route): static
    {
        $this->routesByMethod[HTTPMethod::POST->value][] = [
            'pattern' => "~^$this->basePath$route->pattern$~",
            'class' => $route->class,
            'regex' => $route->pattern
        ];
        return $this;
    }

    /**
     * Exécute une requête et retourne la réponse HTTP correspondante.
     *
     * Recherche une route correspondante selon la méthode HTTP et le chemin de la requête.
     * Si une route correspond, instancie le contrôleur associé et appelle sa méthode trigger().
     * Sinon, retourne une réponse 404.
     *
     * @param Request $request La requête HTTP à traiter
     * @return HTTPResponse La réponse HTTP générée par le contrôleur ou une erreur 404
     *
     * @example
     * ```php
     * $request = Request::fromHTTP();
     * $response = $api->execute($request);
     * // $response contient la réponse du contrôleur ou une erreur 404
     * ```
     */
    public function execute(Request $request): HTTPResponse
    {
        $routes = $this->routesByMethod[$request->method->value] ?? [];
        foreach ($routes as $compiled) {
            if (preg_match($compiled['pattern'], $request->path, $matches)) {
                unset($matches[0]);
                return (new $compiled['class'])->trigger($request, $matches);
            }
        }
        return new HTTPResponse(code: 404, messages: [
            new Message("Route not found {$request->method->value}:$request->path", MessageType::Error),
        ]);
    }

    /**
     * Exécute la requête et écrit la sortie JSON dans la sortie standard.
     *
     * Cette méthode est un raccourci qui appelle execute() puis output() sur la réponse.
     * Elle termine l'exécution du script après l'envoi de la réponse.
     *
     * @param Request $request La requête HTTP à traiter
     * @return void Ne retourne jamais (termine l'exécution du script)
     *
     * @example
     * ```php
     * $api->trigger(Request::fromHTTP());
     * // Le script se termine après l'envoi de la réponse JSON
     * ```
     */
    public function trigger(Request $request): void
    {
        $this->execute($request)->output();
    }

    // --- private ---

    private array $routesByMethod = [];
}
