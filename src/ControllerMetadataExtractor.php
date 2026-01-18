<?php

declare(strict_types=1);

namespace Tivins\Webapp;

use ReflectionAttribute;
use ReflectionException;
use ReflectionMethod;

/**
 * Extrait les métadonnées depuis les handlers de routes (classes, closures, callables).
 *
 * Cette classe supporte deux sources de métadonnées :
 * 1. Les attributs PHP `#[RouteAttribute]` (prioritaires)
 * 2. Les commentaires PHPDoc (fallback)
 *
 * Les attributs RouteAttribute sont recherchés sur :
 * - La méthode `trigger()` pour les classes implémentant RouteInterface
 * - La méthode spécifiée pour les callable arrays [Class::class, 'method']
 * - Les closures ne supportent pas les attributs (PHPDoc utilisé)
 *
 * Le type de retour (`returnType`) est extrait selon l'ordre de priorité :
 * 1. `RouteAttribute->returnType` (explicite, prioritaire)
 * 2. PHPDoc `@return` avec parsing du type
 */
class ControllerMetadataExtractor
{
    /**
     * Extrait les métadonnées depuis un handler de route.
     *
     * Priorité d'extraction :
     * 1. Attribut RouteAttribute sur la méthode
     * 2. PHPDoc de la méthode/classe/closure
     *
     * @param string|\Closure|array $handler Le handler (nom de classe, closure, ou callable array)
     * @return array{summary: string, description: string, responses: array, contentType: ?ContentType, tags: array, deprecated: bool, operationId: string, returnType: string|array|null}
     */
    public function extract(string|\Closure|array $handler): array
    {
        // 1. Tenter d'extraire depuis RouteAttribute (prioritaire)
        $attributeMetadata = $this->extractFromAttribute($handler);
        if ($attributeMetadata !== null) {
            // Si returnType n'est pas défini dans l'attribut, essayer PHPDoc
            if (empty($attributeMetadata['returnType'])) {
                $docComment = $this->getDocComment($handler);
                $attributeMetadata['returnType'] = $this->extractReturnTypeFromDoc($docComment);
            }
            return $attributeMetadata;
        }

        // 2. Fallback sur PHPDoc
        $docComment = $this->getDocComment($handler);

        return [
            'summary' => $this->extractSummaryFromDoc($docComment),
            'description' => $this->extractDescriptionFromDoc($docComment),
            'responses' => $this->extractResponsesFromDoc($docComment),
            'contentType' => null,
            'tags' => [],
            'deprecated' => false,
            'operationId' => '',
            'returnType' => $this->extractReturnTypeFromDoc($docComment),
        ];
    }

    /**
     * Extrait les métadonnées depuis un attribut RouteAttribute.
     *
     * Extrait d'abord les attributs de la classe, puis ceux de la méthode.
     * Les valeurs de la méthode surchargent celles de la classe.
     * Les tags sont fusionnés (tags de la méthode ajoutés à ceux de la classe).
     *
     * @param string|\Closure|array $handler Le handler
     * @return array|null Les métadonnées ou null si aucun attribut trouvé
     */
    private function extractFromAttribute(string|\Closure|array $handler): ?array
    {
        $reflectionMethod = $this->getReflectionMethod($handler);
        if ($reflectionMethod === null) {
            return null;
        }

        // 1. Extraire les attributs de la classe
        $classAttributes = [];
        $reflectionClass = $reflectionMethod->getDeclaringClass();
        $classRouteAttributes = $reflectionClass->getAttributes(
            RouteAttribute::class,
            ReflectionAttribute::IS_INSTANCEOF
        );
        if (!empty($classRouteAttributes)) {
            /** @var RouteAttribute $classAttribute */
            $classAttribute = $classRouteAttributes[0]->newInstance();
            $classAttributes = [
                'summary' => $classAttribute->name,
                'description' => $classAttribute->description,
                'contentType' => $classAttribute->contentType,
                'tags' => $classAttribute->tags,
                'deprecated' => $classAttribute->deprecated,
                'operationId' => $classAttribute->operationId,
                'returnType' => $classAttribute->returnType,
            ];
        }

        // 2. Extraire les attributs de la méthode
        $methodAttributes = [];
        $methodRouteAttributes = $reflectionMethod->getAttributes(
            RouteAttribute::class,
            ReflectionAttribute::IS_INSTANCEOF
        );
        if (!empty($methodRouteAttributes)) {
            /** @var RouteAttribute $methodAttribute */
            $methodAttribute = $methodRouteAttributes[0]->newInstance();
            $methodAttributes = [
                'summary' => $methodAttribute->name,
                'description' => $methodAttribute->description,
                'contentType' => $methodAttribute->contentType,
                'tags' => $methodAttribute->tags,
                'deprecated' => $methodAttribute->deprecated,
                'operationId' => $methodAttribute->operationId,
                'returnType' => $methodAttribute->returnType,
            ];
        }

        // Si aucun attribut trouvé (ni classe ni méthode), retourner null
        if (empty($classAttributes) && empty($methodAttributes)) {
            return null;
        }

        // 3. Fusionner : valeurs de la classe par défaut, surchargées par celles de la méthode
        // Pour returnType, la méthode a priorité, sinon la classe
        $returnType = !empty($methodAttributes['returnType'])
            ? $methodAttributes['returnType']
            : ($classAttributes['returnType'] ?? '');

        $merged = [
            'summary' => $methodAttributes['summary'] ?? $classAttributes['summary'] ?? '',
            'description' => $methodAttributes['description'] ?? $classAttributes['description'] ?? '',
            'contentType' => $methodAttributes['contentType'] ?? $classAttributes['contentType'] ?? ContentType::JSON,
            'tags' => array_unique(array_merge($classAttributes['tags'] ?? [], $methodAttributes['tags'] ?? [])),
            'deprecated' => $methodAttributes['deprecated'] ?? $classAttributes['deprecated'] ?? false,
            'operationId' => $methodAttributes['operationId'] ?? $classAttributes['operationId'] ?? '',
            'returnType' => $returnType,
            'responses' => [],
        ];

        return $merged;
    }

    /**
     * Obtient la ReflectionMethod pour un handler.
     *
     * @param string|\Closure|array $handler Le handler
     * @return ReflectionMethod|null La méthode de réflection ou null
     */
    private function getReflectionMethod(string|\Closure|array $handler): ?ReflectionMethod
    {
        // Cas 1: Nom de classe (string) - chercher la méthode trigger()
        if (is_string($handler)) {
            if (!class_exists($handler)) {
                return null;
            }
            try {
                return new ReflectionMethod($handler, 'trigger');
            } catch (ReflectionException) {
                return null;
            }
        }

        // Cas 2: Closure - pas de support des attributs
        if ($handler instanceof \Closure) {
            return null;
        }

        // Cas 3: Callable array [Class::class, 'method'] ou [$object, 'method']
        if (is_array($handler) && count($handler) === 2) {
            [$classOrObject, $method] = $handler;
            try {
                return new ReflectionMethod($classOrObject, $method);
            } catch (ReflectionException) {
                return null;
            }
        }

        return null;
    }

    /**
     * Récupère le PHPDoc selon le type de handler.
     *
     * @param string|\Closure|array $handler Le handler
     * @return string Le commentaire PHPDoc ou chaîne vide
     */
    private function getDocComment(string|\Closure|array $handler): string
    {
        // Cas 1: Nom de classe (string)
        if (is_string($handler)) {
            if (!class_exists($handler)) {
                return '';
            }
            $reflection = new \ReflectionClass($handler);
            return $reflection->getDocComment() ?: '';
        }

        // Cas 2: Closure
        if ($handler instanceof \Closure) {
            $reflection = new \ReflectionFunction($handler);
            return $reflection->getDocComment() ?: '';
        }

        // Cas 3: Callable array [Class::class, 'method'] ou [$object, 'method']
        if (is_array($handler) && count($handler) === 2) {
            [$classOrObject, $method] = $handler;
            try {
                $reflection = new \ReflectionMethod($classOrObject, $method);
                return $reflection->getDocComment() ?: '';
            } catch (\ReflectionException) {
                return '';
            }
        }

        return '';
    }

    /**
     * Extrait le résumé depuis le PHPDoc.
     * Prend la première ligne non vide du commentaire.
     */
    private function extractSummaryFromDoc(string $docComment): string
    {
        if (empty($docComment)) {
            return '';
        }

        $lines = explode("\n", $docComment);
        foreach ($lines as $line) {
            $line = trim($line);
            // Ignorer les balises de début/fin et les lignes vides
            if (empty($line) || $line === '/**' || $line === '*/' || str_starts_with($line, '* @')) {
                continue;
            }
            // Enlever le * au début si présent
            $line = preg_replace('/^\s*\*\s*/', '', $line);
            if (!empty($line)) {
                return $line;
            }
        }

        return '';
    }

    /**
     * Extrait la description complète depuis le PHPDoc.
     * Prend toutes les lignes jusqu'à la première annotation.
     */
    private function extractDescriptionFromDoc(string $docComment): string
    {
        if (empty($docComment)) {
            return '';
        }

        $lines = explode("\n", $docComment);
        $description = [];

        foreach ($lines as $line) {
            $line = trim($line);
            // Arrêter à la première annotation
            if (str_starts_with($line, '* @')) {
                break;
            }
            // Ignorer les balises de début/fin et les lignes vides
            if (empty($line) || $line === '/**' || $line === '*/') {
                continue;
            }
            // Enlever le * au début si présent
            $line = preg_replace('/^\s*\*\s*/', '', $line);
            if (!empty($line)) {
                $description[] = $line;
            }
        }

        return implode(' ', $description);
    }

    /**
     * Extrait les informations de réponse depuis le PHPDoc.
     * Pour l'instant, retourne un tableau vide (peut être enrichi plus tard).
     */
    private function extractResponsesFromDoc(string $docComment): array
    {
        // TODO: Parser les annotations @return ou @response dans le PHPDoc
        // Pour l'instant, on retourne un tableau vide
        return [];
    }

    /**
     * Extrait le type de retour depuis le PHPDoc.
     *
     * Parse l'annotation @return pour extraire le type de retour.
     * Supporte les formats :
     * - @return User
     * - @return User[]
     * - @return HTTPResponse<User>
     * - @return \App\Models\User
     *
     * @param string $docComment Le commentaire PHPDoc
     * @return string|null Le type de retour ou null si non trouvé
     */
    private function extractReturnTypeFromDoc(string $docComment): ?string
    {
        if (empty($docComment)) {
            return null;
        }

        // Pattern pour extraire @return type
        // Supporte: User, User[], HTTPResponse<User>, \Namespace\Class
        if (preg_match('/@return\s+(\S+)/', $docComment, $matches)) {
            $type = $matches[1];

            // Ignorer les types génériques PHP comme HTTPResponse (garder le type complet)
            // Si c'est HTTPResponse<Type>, extraire Type
            if (preg_match('/^HTTPResponse<(.+)>$/', $type, $genericMatches)) {
                return $genericMatches[1];
            }

            // Ignorer les types primitifs qui ne sont pas utiles pour les schémas
            $ignoredTypes = ['void', 'null', 'self', 'static', 'mixed', 'HTTPResponse'];
            if (in_array($type, $ignoredTypes, true)) {
                return null;
            }

            return $type;
        }

        return null;
    }
}
