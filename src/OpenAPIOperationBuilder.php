<?php

declare(strict_types=1);

namespace Tivins\Webapp;

/**
 * Construit les objets d'opération OpenAPI.
 *
 * Cette classe est responsable de la construction des opérations OpenAPI
 * à partir des métadonnées des routes. Elle utilise `OpenAPISchemaBuilder`
 * pour générer les schémas des réponses à partir des types de retour.
 */
class OpenAPIOperationBuilder
{
    /**
     * @param OpenAPISchemaBuilder|null $schemaBuilder Le constructeur de schémas (optionnel pour la rétrocompatibilité)
     */
    public function __construct(
        private ?OpenAPISchemaBuilder $schemaBuilder = null
    ) {
        // Créer une instance par défaut si non fournie
        $this->schemaBuilder ??= new OpenAPISchemaBuilder();
    }

    /**
     * Retourne le constructeur de schémas.
     *
     * Permet à `OpenAPIGenerator` d'accéder aux schémas générés
     * pour les ajouter à la section `components/schemas`.
     *
     * @return OpenAPISchemaBuilder
     */
    public function getSchemaBuilder(): OpenAPISchemaBuilder
    {
        return $this->schemaBuilder;
    }

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
        // Utiliser operationId depuis les métadonnées ou le générer
        $operationId = !empty($metadata['operationId'])
            ? $metadata['operationId']
            : $this->generateOperationId($route['regex'], $method);

        // Déterminer le contentType pour les réponses
        $contentType = $metadata['contentType'] ?? null;

        // Extraire le type de retour pour générer les schémas
        $returnType = $metadata['returnType'] ?? null;

        $operation = [
            'summary' => $metadata['summary'] ?: ucfirst(strtolower($method)) . ' operation',
            'description' => $metadata['description'] ?? '',
            'operationId' => $operationId,
            'responses' => $metadata['responses'] ?: $this->getDefaultResponses($method, $contentType, $returnType),
        ];

        // Ajouter les tags si présents
        if (!empty($metadata['tags'])) {
            $operation['tags'] = $metadata['tags'];
        }

        // Ajouter deprecated si true
        if (!empty($metadata['deprecated'])) {
            $operation['deprecated'] = true;
        }

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
     *
     * Si `returnType` est un tableau (mapping code => type), utilise `buildResponsesFromMapping()`.
     * Sinon, génère des réponses par défaut avec le schéma spécifié pour 200 (et 201 pour POST/PUT).
     *
     * @param string $method La méthode HTTP
     * @param ContentType|null $contentType Le type de contenu (null = application/json)
     * @param string|array|null $returnType Le type de retour pour générer le schéma
     */
    private function getDefaultResponses(
        string $method,
        ?ContentType $contentType = null,
        string|array|null $returnType = null
    ): array {
        $mimeType = $this->contentTypeToMime($contentType);

        // Si returnType est un tableau de mapping code => type, traiter spécialement
        if (is_array($returnType)) {
            return $this->buildResponsesFromMapping($returnType, $mimeType);
        }

        $schema = $this->getSchemaForContentType($contentType, $returnType);
        $errorSchema = $this->getStandardErrorSchema();

        $responses = [
            '200' => [
                'description' => $this->getStandardHttpDescription('200'),
                'content' => [
                    $mimeType => [
                        'schema' => $schema,
                    ],
                ],
            ],
            '404' => [
                'description' => $this->getStandardHttpDescription('404'),
                'content' => [
                    'application/json' => [
                        'schema' => $errorSchema,
                    ],
                ],
            ],
            '500' => [
                'description' => $this->getStandardHttpDescription('500'),
                'content' => [
                    'application/json' => [
                        'schema' => $errorSchema,
                    ],
                ],
            ],
        ];

        // Pour POST/PUT, ajouter 201 Created
        if (in_array($method, ['POST', 'PUT'], true)) {
            $responses['201'] = [
                'description' => $this->getStandardHttpDescription('201'),
                'content' => [
                    $mimeType => [
                        'schema' => $schema,
                    ],
                ],
            ];
        }

        return $responses;
    }

    /**
     * Construit les réponses depuis un mapping code HTTP => type.
     *
     * Cette méthode génère des réponses OpenAPI avec des schémas différents selon
     * le code HTTP. Elle inclut automatiquement des descriptions standard pour les
     * codes HTTP courants et des schémas d'erreur par défaut pour les codes d'erreur.
     *
     * @param array $mapping Le mapping code => type (ex: ['200' => 'User', '404' => 'Error'])
     * @param string $mimeType Le type MIME à utiliser
     * @return array Les réponses OpenAPI
     */
    private function buildResponsesFromMapping(array $mapping, string $mimeType): array
    {
        $responses = [];
        $errorSchema = $this->getStandardErrorSchema();

        foreach ($mapping as $code => $type) {
            $codeStr = (string)$code;
            $description = $this->getStandardHttpDescription($codeStr);

            // Pour les codes d'erreur (4xx, 5xx) avec type 'object', utiliser le schéma d'erreur standard
            $isErrorCode = $this->isErrorCode($codeStr);
            if ($isErrorCode && $type === 'object') {
                $schema = $errorSchema;
            } else {
                // Construire le schéma depuis le type spécifié
                $schema = $this->buildSchemaFromReturnType($type);
            }

            $responses[$codeStr] = [
                'description' => $description,
                'content' => [
                    $mimeType => [
                        'schema' => $schema,
                    ],
                ],
            ];
        }

        // Ajouter les réponses par défaut pour les codes d'erreur standards si non définies
        $this->addDefaultErrorResponses($responses, $mimeType);

        return $responses;
    }

    /**
     * Vérifie si un code HTTP est un code d'erreur (4xx ou 5xx).
     *
     * @param string $code Le code HTTP
     * @return bool True si c'est un code d'erreur
     */
    private function isErrorCode(string $code): bool
    {
        $codeInt = (int)$code;
        return ($codeInt >= 400 && $codeInt < 600);
    }

    /**
     * Retourne le schéma d'erreur standard pour les codes 4xx et 5xx.
     *
     * @return array Le schéma d'erreur OpenAPI
     */
    private function getStandardErrorSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'error' => [
                    'type' => 'string',
                    'description' => 'Message d\'erreur',
                ],
                'messages' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'field' => [
                                'type' => 'string',
                                'description' => 'Nom du champ en erreur',
                            ],
                            'message' => [
                                'type' => 'string',
                                'description' => 'Message d\'erreur pour ce champ',
                            ],
                        ],
                    ],
                    'description' => 'Liste des erreurs de validation par champ',
                ],
            ],
        ];
    }

    /**
     * Retourne la description standard pour un code HTTP.
     *
     * @param string $code Le code HTTP (ex: '200', '404')
     * @return string La description standard
     */
    private function getStandardHttpDescription(string $code): string
    {
        return match ($code) {
            // Codes de succès (2xx)
            '200' => 'Success',
            '201' => 'Created',
            '202' => 'Accepted',
            '204' => 'No Content',
            // Codes d'erreur client (4xx)
            '400' => 'Bad Request',
            '401' => 'Unauthorized',
            '403' => 'Forbidden',
            '404' => 'Not Found',
            '405' => 'Method Not Allowed',
            '409' => 'Conflict',
            '422' => 'Unprocessable Entity',
            '429' => 'Too Many Requests',
            // Codes d'erreur serveur (5xx)
            '500' => 'Internal Server Error',
            '502' => 'Bad Gateway',
            '503' => 'Service Unavailable',
            '504' => 'Gateway Timeout',
            default => 'Response',
        };
    }

    /**
     * Ajoute les réponses d'erreur par défaut si elles ne sont pas déjà définies.
     *
     * Ajoute automatiquement des schémas d'erreur standards pour les codes 500, 400, 401, 403, 422, 429
     * si ces codes ne sont pas déjà présents dans le mapping.
     *
     * @param array $responses Les réponses déjà construites (modifié par référence)
     * @param string $mimeType Le type MIME à utiliser
     */
    private function addDefaultErrorResponses(array &$responses, string $mimeType): void
    {
        $errorSchema = $this->getStandardErrorSchema();

        // Ajouter 500 si non défini (toujours présent)
        if (!isset($responses['500'])) {
            $responses['500'] = [
                'description' => $this->getStandardHttpDescription('500'),
                'content' => [
                    $mimeType => [
                        'schema' => $errorSchema,
                    ],
                ],
            ];
        }

        // Pour les autres codes d'erreur standards, on ne les ajoute pas automatiquement
        // car ils doivent être explicitement définis dans le mapping si nécessaire
    }

    /**
     * Convertit un ContentType en type MIME OpenAPI.
     */
    private function contentTypeToMime(?ContentType $contentType): string
    {
        if ($contentType === null || $contentType === ContentType::AUTO) {
            return 'application/json';
        }

        // ContentType::value contient déjà le type MIME
        return $contentType->value;
    }

    /**
     * Retourne le schéma OpenAPI approprié pour un ContentType.
     *
     * @param ContentType|null $contentType Le type de contenu
     * @param string|null $returnType Le type de retour (classe Mappable, tableau, etc.)
     * @return array Le schéma OpenAPI
     */
    private function getSchemaForContentType(?ContentType $contentType, ?string $returnType = null): array
    {
        if ($contentType === null || $contentType === ContentType::AUTO || $contentType === ContentType::JSON) {
            // Si un type de retour est spécifié, générer le schéma correspondant
            if ($returnType !== null && $returnType !== '') {
                return $this->buildSchemaFromReturnType($returnType);
            }
            // Par défaut, utiliser 'object' (compatibilité ascendante)
            return ['type' => 'object'];
        }

        // Pour les types textuels (HTML, XML, CSV, TEXT), utiliser string
        return ['type' => 'string'];
    }

    /**
     * Construit un schéma OpenAPI depuis un type de retour.
     *
     * Utilise `OpenAPISchemaBuilder` pour générer le schéma approprié.
     *
     * @param string $returnType Le type de retour (ex: 'User', 'User[]', '\App\Models\User')
     * @return array Le schéma OpenAPI
     */
    private function buildSchemaFromReturnType(string $returnType): array
    {
        return $this->schemaBuilder->buildFromTypeName($returnType);
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
