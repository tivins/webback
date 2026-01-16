# Request

Classe readonly représentant une requête HTTP avec parsing automatique des superglobales PHP.

## Principe

- **Readonly** : immuable après construction
- **Propriétés publiques** : accès direct aux données de la requête
- **Parsing automatique** : `fromHTTP()` extrait tout depuis les superglobales
- **Décodage JWT lazy** : `getTokenData()` décode et cache le token Bearer

## Usage de base

```php
// Dans un contrôleur RouteInterface
public function trigger(Request $request, array $matches): HTTPResponse
{
    // Accès direct aux propriétés
    if ($request->method === HTTPMethod::POST) {
        $userData = $request->body; // Objet décodé depuis JSON
    }
    
    // Authentification via JWT
    $tokenData = $request->getTokenData();
    if (!$tokenData) {
        return new HTTPResponse(401, ['error' => 'Unauthorized']);
    }
    $userId = $tokenData['user_id'];
    
    return new HTTPResponse(200, ['user_id' => $userId]);
}
```

## Exemple concret : API REST

```php
// Bootstrap dans index.php
$api = new API();
$api->addRoute(HTTPMethod::GET, '/api/users/{id}', UserController::class);
$api->trigger(Request::fromHTTP());

// UserController.php
class UserController implements RouteInterface
{
    public function trigger(Request $request, array $matches): HTTPResponse
    {
        $userId = (int) $matches[1];
        
        // Vérification authentification
        $tokenData = $request->getTokenData();
        if (!$tokenData || $tokenData['user_id'] !== $userId) {
            return new HTTPResponse(403, ['error' => 'Forbidden']);
        }
        
        // Récupération utilisateur
        $user = Registries::get(UserRegistry::class)->find($userId);
        if (!$user) {
            return new HTTPResponse(404, ['error' => 'User not found']);
        }
        
        // Format de réponse selon Accept header
        $contentType = $request->accept === ContentType::XML 
            ? ContentType::XML 
            : ContentType::JSON;
            
        return new HTTPResponse(200, $user, contentType: $contentType);
    }
}
```

## Propriétés

| Propriété | Type | Description |
|-----------|------|-------------|
| `method` | `HTTPMethod` | GET, POST, PUT, DELETE |
| `path` | `string` | URI de la requête (`$_SERVER['REQUEST_URI']`) |
| `body` | `mixed` | Corps décodé depuis JSON (`php://input`) |
| `bearerToken` | `mixed` | Token extrait de `Authorization: Bearer ...` |
| `accept` | `ContentType` | Type de contenu accepté (premier de la liste `Accept`) |
| `requestTime` | `DateTime` | Timestamp de la requête (`$_SERVER['REQUEST_TIME']`) |

## Méthodes

### `fromHTTP(): Request`

Parse les superglobales PHP et retourne une instance complète.

**Sources :**
- `method` : `$_SERVER['REQUEST_METHOD']` → `HTTPMethod::tryFrom()`
- `path` : `$_SERVER['REQUEST_URI']`
- `body` : `file_get_contents("php://input")` → `json_decode()`
- `bearerToken` : `Authorization` header → strip `Bearer ` prefix
- `accept` : Premier type de la liste `Accept` header
- `requestTime` : `$_SERVER['REQUEST_TIME']` → `DateTime::createFromFormat('U')`

**Note :** Utilise `apache_request_headers()`, nécessite Apache ou fonction équivalente.

### `getTokenData(): ?array`

Décode le JWT Bearer token et retourne le payload.

- **Lazy loading** : décodage au premier appel uniquement
- **Cache** : résultat stocké dans `$tokenData`
- **Retour** : `array` (payload) ou `null` (token invalide/absent/expiré)

## Subtilités

### Décodage JSON du body

`json_decode()` sans second paramètre retourne un `stdClass` ou `null`. Pour un tableau associatif :

```php
// Dans fromHTTP(), modifier :
body: json_decode(file_get_contents("php://input"), true)
```

### Headers case-sensitive

`apache_request_headers()` peut retourner des clés en minuscules selon la config. Le code gère `Authorization` et `authorization` :

```php
bearerToken: str_replace('Bearer ', '', 
    $headers['Authorization'] ?? $headers['authorization'] ?? '')
```

### Accept header parsing

Seul le premier type de la liste `Accept` est extrait :

```php
ContentType::tryFrom(substr($accept, 0, strpos($accept, ','))) 
    ?? ContentType::JSON
```

Si `Accept: application/json, text/html`, seul `application/json` est pris.

### Token invalide vs absent

`getTokenData()` retourne `null` dans les deux cas. Pour distinguer :

```php
if (empty($request->bearerToken)) {
    // Token absent
} elseif (!$request->getTokenData()) {
    // Token invalide/expiré
}
```

### Readonly et mutation

La classe est `readonly`, mais `$tokenData` est mutable (cache interne). C'est une propriété privée, donc conforme au contrat readonly.

### Tests unitaires

Pour tester sans superglobales :

```php
$request = new Request(
    method: HTTPMethod::POST,
    path: '/api/users',
    body: ['name' => 'Test'],
    bearerToken: 'valid.jwt.token',
    accept: ContentType::JSON
);
```
