# Webback

## Database

Pour utiliser une ou plusieurs bases de données, il faut créer autant de connecteurs qui de bases liées.
Il existe différents connecteurs pour chaque type de base de données (SQLite, MySQL, etc.).

* MySQL
    ```php
    use \Tivins\Webapp\MySQLConnector;
    use \Tivins\Webapp\Database;
    
    $connector = new MySQLConnector('my_db', 'root', 'password');
    $database = new Database($connector);
    ```
  
    Notez que vous pouvez également configurer le `host` et le `port`.

* SQLite

    ```php
    use \Tivins\Webapp\SQLiteConnector;
    use \Tivins\Webapp\Database;
    
    $connector = new SQLiteConnector(__dir__ . '/data/db.sqlite');
    $database = new Database($connector);
    ```

* `NativeConnector`

    ```php
    use \Tivins\Webapp\NativeConnector;
    use \Tivins\Webapp\Database;
    use \Tivins\Webapp\DatabaseType;

    $pdo = getExistingPDOInstanceFromAnywhere();
    $connector = new \Tivins\Webapp\NativeConnector($pdo, DatabaseType::MySql);
    $database = new Database($connector);
    ```


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
// Initialisation (une seule fois au démarrage de l'application)
$database = new Database(new MySQLConnector(...));
Registries::init($database);

class MyApplicationRegistries extends Registries
{
    public static function myObjects(): MyObjectRegistry
    {
        return self::get(MyObjectRegistry::class);
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
use \Tivins\Webapp\RouteInterface;
use \Tivins\Webapp\HTTPResponse;
use \Tivins\Webapp\Request;
use \Tivins\Webapp\HTTPResponse;
use \Tivins\Webapp\ContentType;

class MyRoute implements RouteInterface {
    public function trigger(Request $request,array $matches) : HTTPResponse{
        return new HTTPResponse(200, ['hello' => 'world'], contentType: ContentType::JSON);
    }
}
```
Configure routes :

```php
$api = new \Tivins\Webapp\API();
$api->setRoutes([
    new \Tivins\Webapp\RouteConfig('/users', MyRoute::class, \Tivins\Webapp\HTTPMethod::GET),
    // ...
]);

# Or using fluent style

$api->get('/users', MyRoute::class)
    ->post('/sign-in', MySignInRoute::class);

# Or using closures for simple routes

$api->get('/health', fn($req, $matches) => new HTTPResponse(200, ['status' => 'ok']))
    ->get('/users/(\d+)', fn($req, $matches) => new HTTPResponse(200, ['id' => $matches[1]]));

# Or using callable arrays

$api->get('/test', [MyController::class, 'handleTest']);
```
Trigger the API

* From HTTP 
    ```php
    $api->trigger(\Tivins\Webapp\Request::fromHTTP());
    ```

* From a hand-crafted Request:
    ```php
    $request = new Request(
        method:         HTTPMethod::POST, 
        path:           '/api/users', 
        body:           ['name' => 'John', 'email' => 'john@example.com'], 
        bearerToken:    'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...', 
        accept:         ContentType::JSON 
    );
    $api->trigger($request);
    ```

Trigger and get contents :

```php
$response = $api->execute($request);
```

## Dev notes

Using PHP Builtin webserver :

1. Create a router file `pws_router.php`
    
    ```php
    <?php
    require "vendor/autoload.php";
    return \Tivins\Webapp\PIWSRouter::init(__dir__);
    ```
   
2. Create you index.php:

    ```php
    var_dump($_SERVER['REQUEST_URI']);
    ```
   
3. Run the server :

    ```bash
   php -S 127.0.0.1:8000 -t . pws_router.php
    ```
   
4. HTTP Testing

* http://127.0.0.1:8000/ -> index.php, '/'
* http://127.0.0.1:8000/test -> index.php '/test'
* http://127.0.0.1:8000/existing-file.css -> /existing-file.css