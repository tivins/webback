<?php

declare(strict_types=1);

namespace Tivins\Webapp;

/**
 * Construit les objets d'opération OpenAPI.
 */
class OpenAPIOperationBuilder
{
    /**
     * Construit une opération OpenAPI complète.
     *
     * @param string $method La méthode HTTP (GET, POST, etc.)
     * @param array $route Les informations de la route
     * @param array $parameters Les paramètres de chemin OpenAPI
     * @param array $metadata Les métadonnées extraites du contrôleur
     * @return array L'opération OpenAPI
     */
    public function build(
        string $method,
        array $route,
        array $parameters,
        array $metadata
    ): array {
        $operation = [
            'summary' => $metadata['summary'] ?? ucfirst(strtolower($method)) . ' operation',
            'description' => $metadata['description'] ?? '',
            'operationId' => $this->generateOperationId($route['regex'], $method),
            'responses' => $metadata['responses'] ?: $this->getDefaultResponses($method),
        ];

        // Ajouter les paramètres de chemin
        if (!empty($parameters)) {
            $operation['parameters'] = $parameters;
        }

        // Ajouter requestBody pour POST/PUT/PATCH
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $operation['requestBody'] = $this->buildRequestBody($route, $metadata);
        }

        return $operation;
    }

    /**
     * Génère un operationId unique depuis le pattern et la méthode.
     */
    private function generateOperationId(string $pattern, string $method): string
    {
        // Nettoyer le pattern
        $clean = preg_replace('/[^a-zA-Z0-9\/]/', '_', $pattern);
        $clean = trim($clean, '/_');
        $clean = str_replace('/', '_', $clean);

        return strtolower($method) . '_' . $clean;
    }

    /**
     * Retourne les réponses par défaut selon la méthode HTTP.
     */
    private function getDefaultResponses(string $method): array
    {
        $responses = [
            '200' => [
                'description' => 'Success',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                        ],
                    ],
                ],
            ],
            '404' => [
                'description' => 'Not Found',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'messages' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'object'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '500' => [
                'description' => 'Internal Server Error',
            ],
        ];

        // Pour POST/PUT, ajouter 201 Created
        if (in_array($method, ['POST', 'PUT'], true)) {
            $responses['201'] = [
                'description' => 'Created',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                        ],
                    ],
                ],
            ];
        }

        return $responses;
    }

    /**
     * Construit le requestBody pour les méthodes POST/PUT/PATCH.
     */
    private function buildRequestBody(array $route, array $metadata): array
    {
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        // Pourrait être enrichi depuis les métadonnées
                    ],
                ],
            ],
        ];
    }
}
