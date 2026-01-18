<?php

declare(strict_types=1);

namespace Tivins\Webapp;

/**
 * Génère une spécification OpenAPI 3.0.3 à partir des routes enregistrées.
 */
readonly class OpenAPIGenerator
{
    public function __construct(
        private OpenAPIPathConverter        $pathConverter,
        private ControllerMetadataExtractor $metadataExtractor,
        private OpenAPIOperationBuilder     $operationBuilder
    )
    {
    }

    /**
     * Génère une spécification OpenAPI à partir des routes.
     *
     * @param array $routesByMethod Les routes organisées par méthode HTTP
     * @param array $options Options de génération
     * @return array La spécification OpenAPI au format tableau PHP
     */
    public function generate(array $routesByMethod, array $options = []): array
    {
        // 1. Configuration par défaut
        $config = $this->mergeDefaultOptions($options);

        // 2. Structure OpenAPI de base
        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => $config['title'],
                'version' => $config['version'],
                'description' => $config['description'] ?? '',
            ],
            'servers' => $config['servers'] ?? [],
            'paths' => [],
        ];

        // 3. Parcourir toutes les routes par méthode HTTP
        foreach ($routesByMethod as $method => $routes) {
            foreach ($routes as $route) {
                // 4. Convertir le pattern regex en chemin OpenAPI
                $conversion = $this->pathConverter->convert($route['regex']);
                $openApiPath = $conversion['path'];
                $parameters = $conversion['parameters'];

                // 5. Extraire les métadonnées du handler
                $metadata = $this->metadataExtractor->extract($route['handler']);

                // 6. Construire l'opération OpenAPI
                $operation = $this->operationBuilder->build(
                    $method,
                    $route,
                    $parameters,
                    $metadata
                );

                // 7. Ajouter au paths (grouper par chemin)
                if (!isset($spec['paths'][$openApiPath])) {
                    $spec['paths'][$openApiPath] = [];
                }
                $spec['paths'][$openApiPath][strtolower($method)] = $operation;
            }
        }

        return $spec;
    }

    /**
     * Fusionne les options avec les valeurs par défaut.
     */
    private function mergeDefaultOptions(array $options): array
    {
        return [
            'title' => $options['title'] ?? 'API Documentation',
            'version' => $options['version'] ?? '1.0.0',
            'description' => $options['description'] ?? '',
            'servers' => $options['servers'] ?? [],
            'includeControllerDocs' => $options['includeControllerDocs'] ?? true,
        ];
    }
}
