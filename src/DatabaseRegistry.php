<?php

namespace Tivins\Webapp;


use Exception;

/**
 * @template T of Mappable
 */
abstract class DatabaseRegistry
{
    /** @var class-string<T> */
    protected string $class;
    protected string $tableName;
    protected string $primaryKey = 'id';

    public function __construct(
        private readonly Database $database,
    )
    {
    }

    // ═══════════════════════════════════════════════════════════
    // READ OPERATIONS
    // ═══════════════════════════════════════════════════════════

    /**
     * Trouve une entité par sa clé primaire
     * @return T|null
     */
    public function find(mixed $value): ?Mappable
    {
        return $this->findBy($this->primaryKey, $value);
    }

    /**
     * Trouve une entité par un champ spécifique
     * @return T|null
     */
    public function findBy(string $key, mixed $value): ?Mappable
    {
        $data = $this->database->loadBy($this->tableName, $key, $value)->fetch();
        return $data ? (new $this->class())->map($data) : null;
    }

    /**
     * Récupère toutes les entités
     * @return T[]
     */
    public function findAll(): array
    {
        $rows = $this->database->execute("SELECT * FROM $this->tableName")->fetchAll();
        return array_map(fn($row) => (new $this->class())->map($row), $rows);
    }

    /**
     * Récupère toutes les entités correspondant à un critère
     * @return T[]
     */
    public function findAllBy(string $key, mixed $value): array
    {
        $rows = $this->database->loadBy($this->tableName, $key, $value)->fetchAll();
        return array_map(fn($row) => (new $this->class())->map($row), $rows);
    }

    /**
     * Recherche avec multiples conditions
     * @param string $operator 'AND' ou 'OR'
     * @return T[]
     */
    public function findByConditions(Conditions $conditions, string $operator = 'AND'): array
    {
        $rows = $this->database->loadByConditions($this->tableName, $conditions, $operator)->fetchAll();
        return array_map(fn($row) => (new $this->class())->map($row), $rows);
    }

    // ═══════════════════════════════════════════════════════════
    // WRITE OPERATIONS
    // ═══════════════════════════════════════════════════════════

    /**
     * Sauvegarde une entité (insert ou update automatique)
     * @param T $entity
     * @return int L'ID de l'entité
     * @throws Exception
     */
    public function save(Mappable $entity): int
    {
        $entity->validate();
        $pk = $this->primaryKey;
        if ($entity->$pk) {
            return $this->update($entity);
        }
        return $this->insert($entity);
    }

    /**
     * Insère une nouvelle entité
     * @param T $entity
     */
    protected function insert(Mappable $entity): int
    {
        $data = $this->toArray($entity, excludePrimaryKey: true);
        $id = $this->database->insert($this->tableName, $data);
        $entity->{$this->primaryKey} = $id;
        return $id;
    }

    /**
     * Met à jour une entité existante
     * @param T $entity
     */
    protected function update(Mappable $entity): int
    {
        $data = $this->toArray($entity, excludePrimaryKey: true);
        $this->database->update($this->tableName, $data, $this->primaryKey, $entity->{$this->primaryKey});
        return $entity->{$this->primaryKey};
    }

    /**
     * Supprime une entité
     * @param T $entity
     */
    public function delete(Mappable $entity): bool
    {
        $pk = $this->primaryKey;
        return $this->database->delete($this->tableName, $pk, $entity->$pk);
    }

    /**
     * Supprime par ID
     */
    public function deleteById(mixed $id): bool
    {
        return $this->database->delete($this->tableName, $this->primaryKey, $id);
    }

    /**
     * Supprime selon les conditions
     */
    public function deleteByConditions(Conditions $keysValues, string $operator = 'AND'): bool
    {
        return $this->database->deleteByConditions($this->tableName, $keysValues, $operator);
    }

    // ═══════════════════════════════════════════════════════════
    // UTILITY METHODS
    // ═══════════════════════════════════════════════════════════

    /**
     * Convertit l'entité en tableau pour insertion/update
     * @param T $entity
     */
    protected function toArray(Mappable $entity, bool $excludePrimaryKey = false): array
    {
        $data = [];
        $reflection = Mappable::reflection($this->class);
        foreach ($reflection as $field => $type) {
            if ($excludePrimaryKey && $field === $this->primaryKey) continue;
            $value = $entity->$field;
            $data[$field] = match ($type) {
                'DateTime' => $value->format('Y-m-d H:i:s'),
                'bool' => $value ? 1 : 0,
                default => $value,
            };
        }
        return $data;
    }
    // ═══════════════════════════════════════════════════════════
    // ABSTRACT METHODS
    // ═══════════════════════════════════════════════════════════

    /**
     * Crée la table en base de données (migration)
     */
    abstract public function createTable(Database $database): void;
}
