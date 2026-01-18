<?php

declare(strict_types=1);

namespace Tivins\Webapp;

/**
 * Extrait les métadonnées depuis les classes contrôleurs (PHPDoc, réflexion).
 */
class ControllerMetadataExtractor
{
    /**
     * Extrait les métadonnées depuis la classe contrôleur.
     *
     * @param string $className Le nom complet de la classe contrôleur
     * @return array{summary: string, description: string, responses: array}
     */
    public function extract(string $className): array
    {
        if (!class_exists($className)) {
            return [
                'summary' => '',
                'description' => '',
                'responses' => [],
            ];
        }

        $reflection = new \ReflectionClass($className);
        $docComment = $reflection->getDocComment() ?: '';

        return [
            'summary' => $this->extractSummaryFromDoc($docComment),
            'description' => $this->extractDescriptionFromDoc($docComment),
            'responses' => $this->extractResponsesFromDoc($docComment),
        ];
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
}
