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
     * @param array<RouteConfig> $routes Tableau d'objets RouteConfig
     * @return static Instance courante pour le chaînage de méthodes
     *
     * @example
     * ```php
     * $api->setRoutes([
     *     new RouteConfig('/users/(\d+)', UserController::class, HTTPMethod::GET),
     *     new RouteConfig('/users', UsersController::class, HTTPMethod::POST),
     *     new RouteConfig('/health', fn($req, $matches) => new HTTPResponse(200, ['status' => 'ok'])),
     * ]);
     * ```
     */
    public function setRoutes(array $routes): static
    {
        foreach ($routes as $route) {
            $this->routesByMethod[$route->method->value][] = [
                'pattern' => "~^$this->basePath$route->pattern$~",
                'handler' => $route->handler,
                'regex' => $route->pattern
            ];
        }
        return $this;
    }

    /**
     * Enregistre une route GET.
     *
     * @param string $pattern Le pattern de la route (expression régulière)
     * @param string|\Closure|array $handler Le contrôleur ou callable à exécuter
     * @return static Instance courante pour le chaînage de méthodes
     */
    public function get(string $pattern, string|\Closure|array $handler): static
    {
        $this->routesByMethod[HTTPMethod::GET->value][] = [
            'pattern' => "~^$this->basePath$pattern$~",
            'handler' => $handler,
            'regex' => $pattern
        ];
        return $this;
    }

    /**
     * Enregistre une route POST.
     *
     * @param string $pattern Le pattern de la route (expression régulière)
     * @param string|\Closure|array $handler Le contrôleur ou callable à exécuter
     * @return static Instance courante pour le chaînage de méthodes
     */
    public function post(string $pattern, string|\Closure|array $handler): static
    {
        $this->routesByMethod[HTTPMethod::POST->value][] = [
            'pattern' => "~^$this->basePath$pattern$~",
            'handler' => $handler,
            'regex' => $pattern
        ];
        return $this;
    }

    /**
     * Exécute une requête et retourne la réponse HTTP correspondante.
     *
     * Recherche une route correspondante selon la méthode HTTP et le chemin de la requête.
     * Si une route correspond, exécute le handler associé (classe ou callable).
     * Sinon, retourne une réponse 404.
     *
     * @param Request $request La requête HTTP à traiter
     * @return HTTPResponse La réponse HTTP générée par le handler ou une erreur 404
     *
     * @example
     * ```php
     * $request = Request::fromHTTP();
     * $response = $api->execute($request);
     * // $response contient la réponse du handler ou une erreur 404
     * ```
     */
    public function execute(Request $request): HTTPResponse
    {
        $routes = $this->routesByMethod[$request->method->value] ?? [];
        foreach ($routes as $compiled) {
            if (preg_match($compiled['pattern'], $request->path, $matches)) {
                unset($matches[0]);
                return $this->executeHandler($compiled['handler'], $request, $matches);
            }
        }
        return new HTTPResponse(code: 404, messages: [
            new Message("Route not found {$request->method->value}:$request->path", MessageType::Error),
        ]);
    }

    /**
     * Exécute un handler de route (classe ou callable).
     *
     * @param string|\Closure|array $handler Le handler à exécuter
     * @param Request $request La requête HTTP
     * @param array $matches Les captures de l'expression régulière
     * @return HTTPResponse La réponse HTTP
     */
    private function executeHandler(string|\Closure|array $handler, Request $request, array $matches): HTTPResponse
    {
        // Si c'est une string, c'est un nom de classe qui implémente RouteInterface
        if (is_string($handler)) {
            return (new $handler)->trigger($request, $matches);
        }

        // Sinon, c'est un callable (Closure ou array)
        return $handler($request, $matches);
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

    /**
     * Génère une spécification OpenAPI 3.0.3 à partir des routes enregistrées.
     *
     * @param array $options Options de génération :
     *   - 'title' (string): Titre de l'API (défaut: "API Documentation")
     *   - 'version' (string): Version de l'API (défaut: "1.0.0")
     *   - 'description' (string): Description de l'API
     *   - 'servers' (array): Liste des serveurs (ex: [['url' => 'https://api.example.com']])
     *   - 'includeControllerDocs' (bool): Inclure les PHPDoc des contrôleurs (défaut: true)
     *
     * @return array La spécification OpenAPI au format tableau PHP
     *
     * @example
     * ```php
     * $api = new API('/api/v1');
     * $api->get('/users/(\d+)', UserController::class);
     * $spec = $api->generateOpenAPISpec([
     *     'title' => 'My API',
     *     'servers' => [['url' => 'https://api.example.com']]
     * ]);
     * file_put_contents('openapi.json', json_encode($spec, JSON_PRETTY_PRINT));
     * ```
     */
    public function generateOpenAPISpec(array $options = []): array
    {
        $generator = new OpenAPIGenerator(
            new OpenAPIPathConverter(),
            new ControllerMetadataExtractor(),
            new OpenAPIOperationBuilder()
        );

        return $generator->generate($this->routesByMethod, $options);
    }

    // --- private ---

    private array $routesByMethod = [];
}
