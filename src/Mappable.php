<?php

declare(strict_types=1);

namespace Tivins\Webapp;

use DateMalformedStringException;
use DateTime;
use Exception;
use JsonSerializable;
use ReflectionClass;
use ReflectionException;
use ReturnTypeWillChange;

class Mappable implements JsonSerializable
{
    /**
     * Cache de réflexion pour optimiser les performances.
     *
     * Analyse les propriétés d'une classe et retourne un tableau associatif
     * nom de propriété => type de propriété. Le résultat est mis en cache
     * pour éviter les analyses répétées.
     *
     * @param string $class Le nom complet de la classe à analyser
     * @return array<string, string> Tableau associatif [nom_propriété => type]
     *
     * @example
     * ```php
     * class User extends Mappable {
     *     public int $id;
     *     public string $name;
     *     public DateTime $created_at;
     * }
     *
     * $reflection = Mappable::reflection(User::class);
     * // Retourne: ['id' => 'int', 'name' => 'string', 'created_at' => 'DateTime']
     * ```
     */
    public static function reflection(string $class): array
    {
        static $reflection;
        if (!isset($reflection) || !isset($reflection[$class])) {
            try {
                $refClass = new ReflectionClass($class);
                $properties = $refClass->getProperties();
                foreach ($properties as $property) {
                    $type = $property->getType();
                    if ($type === null) {
                        continue;
                    }
                    
                    // Gérer les types union (int|string, etc.)
                    if ($type instanceof \ReflectionUnionType) {
                        $typeNames = [];
                        foreach ($type->getTypes() as $unionType) {
                            if ($unionType instanceof \ReflectionNamedType) {
                                $typeNames[] = $unionType->getName();
                            }
                        }
                        // Représenter les types union comme "int|string"
                        $reflection[$class][$property->getName()] = implode('|', $typeNames);
                    } elseif ($type instanceof \ReflectionNamedType) {
                        $reflection[$class][$property->getName()] = $type->getName();
                    }
                }
                // var_dump("REFLECTION ANALYSIS = ", $reflection);
            } catch (ReflectionException) {
                // TODO Log error
            }
        }
        return $reflection[$class] ?? [];
    }

    /**
     * Mappe un objet stdClass (résultat PDO) vers l'entité
     *
     * @throws DateMalformedStringException
     */
    public function map(object|array $object): static
    {
        $reflection = static::reflection(static::class);
        foreach ($object as $field => $value) {
            $fieldType = $reflection[$field] ?? null;
            if (!$fieldType) {
                continue;
            }
            $this->$field = match ($fieldType) {
                'int' => (int)$value,
                'DateTime' => new DateTime($value),
                'bool' => (bool)$value,
                'float' => (float)$value,
                default => $value,
            };
        }
        return $this;
    }

    /**
     * Sérialise l'objet en JSON.
     *
     * Convertit l'entité en tableau pour la sérialisation JSON.
     * Les objets DateTime sont automatiquement convertis au format ISO 8601.
     *
     * @return array<string, mixed> Tableau associatif des propriétés de l'objet
     *
     * @example
     * ```php
     * $user = new User();
     * $user->id = 1;
     * $user->name = 'John';
     * $user->created_at = new DateTime();
     *
     * $json = json_encode($user);
     * // {"id":1,"name":"John","created_at":"2026-01-15T10:30:00+00:00"}
     * ```
     */
    #[ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        $reflection = static::reflection(static::class);
        $data = get_object_vars($this);
        foreach ($data as $field => $value) {
            $fieldType = $reflection[$field] ?? null;
            if (!$fieldType) {
                continue;
            }
            if ($fieldType == 'DateTime') {
                $data[$field] = $this->$field->format(DATE_ATOM);
            }
        }
        return $data;
    }

    /**
     * Valide l'entité.
     *
     * Parcourt toutes les propriétés de l'entité et appelle validateField()
     * pour chaque propriété. Les classes enfants peuvent surcharger validateField()
     * pour implémenter une validation personnalisée.
     *
     * @return void
     * @throws Exception Peut lever des exceptions si la validation échoue
     *
     * @example
     * ```php
     * class User extends Mappable {
     *     protected function validateField(string $field, mixed $value): mixed {
     *         if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
     *             throw new Exception('Email invalide');
     *         }
     *         return $value;
     *     }
     * }
     *
     * $user = new User();
     * $user->email = 'invalid-email';
     * $user->validate(); // Lève une exception
     * ```
     */
    public function validate(): void {
        $reflection = static::reflection(static::class);
        foreach ($reflection as $field => $value) {
            $this->validateField($field, $this->$field);
        }
    }


    protected function validateField(string $field, mixed $value): mixed {
        return $value; // Override dans les classes enfants
    }
}
