# Registries

Gestionnaire centralisé d'instances de `DatabaseRegistry` avec lazy loading et pattern singleton.

## Principe

- **Singleton par classe+database** : une seule instance par couple `(RegistryClass, Database)`
- **Lazy loading** : instanciation au premier appel
- **Database par défaut** : définie via `init()`, utilisable ensuite sans paramètre

## Usage de base

```php
// Bootstrap
Registries::init($database);

// Récupération (crée l'instance au premier appel)
$users = Registries::get(UserRegistry::class);
$users2 = Registries::get(UserRegistry::class); // même instance
```

## Extension recommandée

Créer une classe dérivée pour le typage statique et l'autocomplétion :

```php
class AppRegistries extends Registries
{
    public static function users(): UserRegistry
    {
        return self::get(UserRegistry::class);
    }
    
    public static function products(): ProductRegistry
    {
        return self::get(ProductRegistry::class);
    }
}

// Usage
$user = AppRegistries::users()->find(42);
```

## Multi-database

Possibilité d'associer une database spécifique à une registry :

```php
Registries::init($mainDb);

// UserRegistry utilisera $analyticsDb au lieu de $mainDb
$users = Registries::get(UserRegistry::class, $analyticsDb);

// Les appels suivants conservent l'association
$users2 = Registries::get(UserRegistry::class); // utilise $analyticsDb
```

## Tests

```php
// Libérer une registry spécifique (conserve l'association database)
Registries::release(UserRegistry::class);

// Supprimer l'association database d'une classe
Registries::unbind(UserRegistry::class);

// Reset complet : instances, database par défaut, associations
Registries::reset();
```

## Subtilités

### Clé d'instance

La clé interne est `ClassName|spl_object_id(Database)`. Conséquences :

- Deux objets `Database` distincts (même config) = deux instances de registry
- Permet le multi-tenant avec databases séparées

### Priorité de résolution de la Database

1. Paramètre `$database` fourni (et mémorisé)
2. Association précédemment stockée pour cette classe
3. Database par défaut (`init()`)
4. Exception si rien

### Différence entre `release()`, `unbind()` et `reset()`

| Méthode | Instances | Association | Database par défaut |
|---------|-----------|-------------|---------------------|
| `release($class)` | Supprime pour $class | Conserve | Conserve |
| `unbind($class)` | Conserve | Supprime pour $class | Conserve |
| `reset()` | Supprime tout | Supprime tout | Supprime |

Cas d'usage :
- `release()` : recréer une registry avec la même database (état interne modifié)
- `unbind()` : réassocier une classe à une autre database sans perdre les instances existantes
- `reset()` : isolation complète entre tests
