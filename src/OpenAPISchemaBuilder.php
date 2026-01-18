<?php

declare(strict_types=1);

namespace Tivins\Webapp;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * Construit des schémas OpenAPI depuis des classes PHP.
 *
 * Cette classe est responsable de la génération des schémas OpenAPI depuis les classes,
 * notamment les classes `Mappable`. Elle utilise la réflexion PHP pour analyser les
 * propriétés et leurs types.
 *
 * Fonctionnalités :
 * - Support des types primitifs (int, float, bool, string, DateTime)
 * - Mise en cache des schémas générés pour optimiser les performances
 * - Génération de références `$ref` pour éviter la duplication
 *
 * @example
 * ```php
 * $builder = new OpenAPISchemaBuilder();
 * $schema = $builder->buildFromTypeName('int');
 * // Retourne: ['type' => 'integer']
 *
 * $schema = $builder->buildFromTypeName('User');
 * // Retourne: ['$ref' => '#/components/schemas/User'] si User étend Mappable
 * ```
 */
class OpenAPISchemaBuilder
{
    /**
     * Cache des schémas générés pour éviter la duplication.
     * Structure: ['ClassName' => ['schema' => [...], 'refName' => 'ClassName']]
     */
    private static array $schemaCache = [];

    /**
     * Schémas enregistrés pour la section components/schemas.
     * Structure: ['SchemaName' => schema]
     */
    private array $componentsSchemas = [];

    /**
     * Classes en cours de génération (pour détecter les cycles).
     */
    private array $generatingClasses = [];

    /**
     * Construit un schéma OpenAPI depuis un nom de type PHP.
     *
     * Supporte :
     * - Types primitifs : int, float, bool, string
     * - DateTime : converti en string avec format date-time
     * - Tableaux : notation avec [] (ex: 'User[]')
     * - Classes Mappable : génère une référence $ref
     *
     * @param string $typeName Le nom du type PHP
     * @param array $options Options de génération
     * @return array Le schéma OpenAPI
     */
    public function buildFromTypeName(string $typeName, array $options = []): array
    {
        // Gérer les tableaux (notation User[])
        if (str_ends_with($typeName, '[]')) {
            $itemType = substr($typeName, 0, -2);
            return $this->buildArraySchema($itemType);
        }

        // Types primitifs
        return match ($typeName) {
            'int', 'integer' => ['type' => 'integer'],
            'float', 'double' => ['type' => 'number'],
            'bool', 'boolean' => ['type' => 'boolean'],
            'string' => ['type' => 'string'],
            'array' => ['type' => 'array', 'items' => ['type' => 'object']],
            'object' => ['type' => 'object'],
            'mixed' => ['type' => 'object'],
            'DateTime', '\DateTime' => [
                'type' => 'string',
                'format' => 'date-time',
                'example' => '2026-01-18T10:30:00+00:00',
            ],
            'DateTimeImmutable', '\DateTimeImmutable' => [
                'type' => 'string',
                'format' => 'date-time',
                'example' => '2026-01-18T10:30:00+00:00',
            ],
            default => $this->buildComplexType($typeName, $options),
        };
    }

    /**
     * Construit le schéma pour un type complexe (classe).
     *
     * Si la classe étend Mappable, génère récursivement le schéma avec une référence.
     * Sinon, retourne un schéma object générique.
     *
     * @param string $typeName Le nom complet de la classe
     * @param array $options Options de génération
     * @return array Le schéma OpenAPI ou une référence $ref
     */
    private function buildComplexType(string $typeName, array $options = []): array
    {
        // Si c'est un Mappable, générer le schéma avec référence
        if (class_exists($typeName) && is_subclass_of($typeName, Mappable::class)) {
            return $this->buildFromMappable($typeName, ['useRef' => true] + $options);
        }

        // Sinon, type object générique
        return ['type' => 'object'];
    }

    /**
     * Génère un schéma OpenAPI depuis une classe Mappable.
     *
     * Utilise `Mappable::reflection()` pour analyser les propriétés et leurs types.
     * Les schémas générés sont mis en cache et enregistrés dans components/schemas.
     *
     * @param string $className Le nom complet de la classe (doit étendre Mappable)
     * @param array $options Options de génération :
     *                       - useRef: bool (true) - utiliser $ref pour référencer le schéma
     *                       - includeDescriptions: bool (false) - inclure les descriptions PHPDoc
     * @return array Le schéma OpenAPI ou une référence $ref
     * @throws InvalidArgumentException Si la classe n'étend pas Mappable
     */
    public function buildFromMappable(string $className, array $options = []): array
    {
        if (!class_exists($className) || !is_subclass_of($className, Mappable::class)) {
            throw new InvalidArgumentException("$className must extend Mappable");
        }

        $useRef = $options['useRef'] ?? true;
        $schemaName = $this->getSchemaName($className);

        // Vérifier le cache
        if (isset(self::$schemaCache[$className])) {
            if ($useRef) {
                return ['$ref' => "#/components/schemas/$schemaName"];
            }
            return self::$schemaCache[$className]['schema'];
        }

        // Détecter les cycles (récursion infinie)
        if (in_array($className, $this->generatingClasses, true)) {
            // Si cycle détecté, retourner une référence
            if ($useRef) {
                return ['$ref' => "#/components/schemas/$schemaName"];
            }
            // Sinon, retourner un schéma object générique
            return ['type' => 'object'];
        }

        // Marquer comme en cours de génération
        $this->generatingClasses[] = $className;

        $reflection = Mappable::reflection($className);
        $properties = [];
        $required = [];

        $reflectionClass = new ReflectionClass($className);
        $docComment = $reflectionClass->getDocComment() ?: '';
        $propertyDescriptions = $this->extractPropertyDescriptions($docComment);

        foreach ($reflection as $propertyName => $propertyType) {
            $reflectionProperty = $reflectionClass->getProperty($propertyName);
            $propertyTypeInfo = $reflectionProperty->getType();

            // Gérer les types union avec oneOf
            if ($propertyTypeInfo instanceof ReflectionUnionType) {
                $types = [];
                foreach ($propertyTypeInfo->getTypes() as $type) {
                    if ($type instanceof ReflectionNamedType) {
                        $types[] = $this->buildFromTypeName($type->getName());
                    }
                }
                $properties[$propertyName] = ['oneOf' => $types];
            } elseif ($propertyTypeInfo instanceof ReflectionNamedType) {
                $properties[$propertyName] = $this->buildFromTypeName($propertyTypeInfo->getName());
            } else {
                // Type non typé ou autre
                $properties[$propertyName] = ['type' => 'object'];
            }

            // Ajouter la description si disponible
            if (isset($propertyDescriptions[$propertyName])) {
                $properties[$propertyName]['description'] = $propertyDescriptions[$propertyName];
            }

            // Déterminer si la propriété est requise (pas nullable et pas de valeur par défaut)
            if ($propertyTypeInfo !== null && !$propertyTypeInfo->allowsNull()) {
                if (!$reflectionProperty->hasDefaultValue()) {
                    $required[] = $propertyName;
                }
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        // Ajouter la description de la classe si demandé
        if (!empty($options['includeDescriptions'])) {
            $classDescription = $this->extractClassDescription($docComment);
            if ($classDescription) {
                $schema['description'] = $classDescription;
            }
        }

        // Enregistrer dans le cache et dans components/schemas
        self::$schemaCache[$className] = ['schema' => $schema, 'refName' => $schemaName];
        $this->componentsSchemas[$schemaName] = $schema;

        // Retirer de la liste des classes en cours de génération
        $this->generatingClasses = array_diff($this->generatingClasses, [$className]);

        // Retourner une référence ou le schéma complet selon l'option
        if ($useRef) {
            return ['$ref' => "#/components/schemas/$schemaName"];
        }

        return $schema;
    }

    /**
     * Génère un schéma pour un tableau d'éléments.
     *
     * @param string $itemTypeName Le type des éléments du tableau
     * @return array Le schéma OpenAPI pour le tableau
     */
    public function buildArraySchema(string $itemTypeName): array
    {
        return [
            'type' => 'array',
            'items' => $this->buildFromTypeName($itemTypeName),
        ];
    }

    /**
     * Retourne tous les schémas enregistrés pour components/schemas.
     *
     * @return array Les schémas enregistrés
     */
    public function getComponentsSchemas(): array
    {
        return $this->componentsSchemas;
    }

    /**
     * Réinitialise le cache des schémas.
     *
     * Utile pour les tests ou pour regénérer les schémas.
     */
    public function clearCache(): void
    {
        self::$schemaCache = [];
        $this->componentsSchemas = [];
        $this->generatingClasses = [];
    }

    /**
     * Génère un nom de schéma depuis un nom de classe.
     *
     * Extrait le nom court de la classe sans le namespace.
     * Ex: 'App\Models\User' => 'User'
     *
     * @param string $className Le nom complet de la classe
     * @return string Le nom du schéma
     */
    private function getSchemaName(string $className): string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }

    /**
     * Extrait les descriptions des propriétés depuis le PHPDoc.
     *
     * Parse les annotations @property dans le PHPDoc de la classe.
     * Format attendu : @property type $name Description
     *
     * @param string $docComment Le commentaire PHPDoc
     * @return array<string, string> Tableau [nom_propriété => description]
     */
    private function extractPropertyDescriptions(string $docComment): array
    {
        static $cache = [];
        if (isset($cache[$docComment])) {
            return $cache[$docComment];
        }

        $descriptions = [];

        // Parser @property type $name description
        if (preg_match_all('/@property\s+\S+\s+\$(\w+)\s+(.+)/', $docComment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $descriptions[$match[1]] = trim($match[2]);
            }
        }

        $cache[$docComment] = $descriptions;
        return $descriptions;
    }

    /**
     * Extrait la description de la classe depuis le PHPDoc.
     *
     * Prend la première ligne non vide après /** qui n'est pas une annotation.
     *
     * @param string $docComment Le commentaire PHPDoc
     * @return string|null La description ou null si non trouvée
     */
    private function extractClassDescription(string $docComment): ?string
    {
        $lines = explode("\n", $docComment);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line === '/**' || $line === '*/' || str_starts_with($line, '* @')) {
                continue;
            }
            $line = preg_replace('/^\s*\*\s*/', '', $line);
            if (!empty($line)) {
                return $line;
            }
        }
        return null;
    }
}
