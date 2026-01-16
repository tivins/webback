# Webback

## Database

Pour utiliser une ou plusieurs bases de données, il faut créer autant de connecteurs qui de bases liées.
Il existe différents connecteurs pour chaque type de base de données (SQLite, MySQL, etc.).

```php
use \Tivins\Webapp\MySQLConnector;
use \Tivins\Webapp\Database;

$connector = new MySQLConnector('my_db', 'root', 'password');
$database = new Database($connector);
```

Exemple avec SQLite

```php
use \Tivins\Webapp\SQLiteConnector;
use \Tivins\Webapp\Database;

$connector = new SQLiteConnector(__dir__ . '/data/db.sqlite');
$database = new Database($connector);
```

Voir également `NativeConnector`.

## Mappable & DatabaseRegistry

Pour commencer, nous devons définir un value-objet, qui étend de `Mappable`.

```php
use \Tivins\Webapp\Mappable;

class MyObject extends Mappable {
    public function __construct(
        public int $id = 0,
        public string $title = ''
    )
    { }
}
```

Pour effectuer les opérations en base de données, nous allons créer 
notre classe, en étendant de `DatabaseRegistry`. Nous devons, dans cette 
classe, décrire les éléments nécessaires aux manipulations en base de 
données.

```php
use \Tivins\Webapp\Database;
use \Tivins\Webapp\DatabaseRegistry;

class MyObjectRegistry extends DatabaseRegistry 
{
    protected string $class = MyObject::class;
    protected string $tableName = 'MyObject';
    protected string $primaryKey = 'id';

    public function createTable(Database $database): void
        $helper = $database->getHelper();
        
        $database->execute(
            $helper->createTable($this->tableName,
                $helper->getAutoincrement('id'),
                $helper->getText('title'),
            )
        );
    }
}
```
Ensuite, afin de permettre de gérer les instances de DatabaseRegistry, il est possible d'utiliser le mécanisme `Registries`.

```php
class MyApplicationRegistries extends Registries
{
    public static function myObjects(Database $database): MyObjectRegistry
    {
        return self::get(MyObjectRegistry::class, $database);
    }
}
```

Ainsi, dès qu'il faudra réaliser des opérations sur les objets, nous pourrons utiliser le code suivant :

```php
$myObject1 = new MyObject(title: 'test');
MyApplicationRegistries::myObjects()->save($myObject1);
echo $myObject1->id; # Outputs 1
```

## API 

Une route est représentée par une classe qui implémente `RouteInterface`. 

```php
class MyRoute implements \Tivins\Webapp\RouteInterface
```

```php
$api = new \Tivins\Webapp\API();
$api->setRoutes([

]);
$api->trigger(\Tivins\Webapp\Request::fromHTTP());
```