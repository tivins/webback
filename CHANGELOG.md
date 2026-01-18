# Changelog

Tous les changements notables de ce projet seront documentés dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

## [0.17.0] - 2026-01-18

### Added
- **Support complet des réponses multiples par code HTTP** (Phase 5) :
  - Support étendu des codes HTTP standards avec descriptions automatiques (200, 201, 202, 204, 400, 401, 403, 404, 405, 409, 422, 429, 500, 502, 503, 504)
  - Schémas d'erreur standard automatiques pour les codes 4xx et 5xx lorsque le type est `'object'`
  - Structure d'erreur standard avec propriétés `error` (string) et `messages` (array) pour la validation
  - Ajout automatique de la réponse 500 avec schéma d'erreur si non défini dans le mapping
  - Nouvelle méthode `getStandardHttpDescription()` pour obtenir les descriptions standard des codes HTTP
  - Nouvelle méthode `getStandardErrorSchema()` pour générer le schéma d'erreur standard
  - Nouvelle méthode `isErrorCode()` pour détecter les codes d'erreur (4xx, 5xx)
- 5 nouveaux tests complets pour valider les réponses multiples (168 tests au total, 520 assertions)

### Changed
- `buildResponsesFromMapping()` utilise maintenant le schéma d'erreur standard pour les codes d'erreur (4xx, 5xx) lorsque le type est `'object'`
- `getDefaultResponses()` utilise maintenant `getStandardHttpDescription()` pour toutes les descriptions de codes HTTP
- Les réponses 404 et 500 par défaut utilisent maintenant le schéma d'erreur standard au lieu d'un simple `object`
- Amélioration de la cohérence : toutes les descriptions de codes HTTP utilisent maintenant les descriptions standard

### Fixed
- Correction de la génération des schémas pour les codes d'erreur : utilisation du schéma d'erreur standard au lieu d'un simple `object`

## [0.16.0] - 2026-01-18

### Added
- **Support complet des types complexes** (Phase 4) :
  - Support des types union (`int|string`) avec génération de schémas `oneOf` dans OpenAPI
  - Support des types nullable (`int|null`) avec `nullable: true` dans OpenAPI
  - Amélioration de `Mappable::reflection()` pour supporter les types union (retourne `"int|string"` au lieu de lever une erreur)
  - Tests complets pour les objets imbriqués (Article avec User, etc.)
  - Tests pour la détection et gestion des cycles dans les objets imbriqués
  - Tests pour les types union avec différents cas (union simple, union avec null)
- 7 nouveaux tests ajoutés pour valider les types complexes (163 tests au total, 469 assertions)

### Changed
- `Mappable::reflection()` supporte maintenant les types union et retourne une représentation string (`"int|string"`)
- `OpenAPISchemaBuilder` gère mieux les types union avec null : utilise `nullable: true` pour les unions simples avec null, `oneOf` pour les unions complexes
- Amélioration de la gestion des cycles : les références circulaires sont détectées et gérées avec des références `$ref`

### Fixed
- Correction de `Mappable::reflection()` qui levait une erreur sur les propriétés avec types union

## [0.15.0] - 2026-01-18

### Added
- **Amélioration du parsing PHPDoc pour les descriptions** (Phase 3) :
  - Support de l'annotation `@var` sur les propriétés individuelles pour documenter les propriétés
  - Parsing amélioré de `@property` avec support des descriptions multi-lignes
  - Support de `@property-read` et `@property-write` dans le PHPDoc des classes
  - Les descriptions des propriétés et des classes sont maintenant toujours incluses dans les schémas OpenAPI (plus besoin de `includeDescriptions`)
- Nouvelle méthode `extractVarDescription()` dans `OpenAPISchemaBuilder` pour extraire les descriptions depuis `@var`
- Tests complets pour valider le parsing PHPDoc avec différents formats

### Changed
- **BREAKING**: Les descriptions sont maintenant toujours incluses dans les schémas générés par `buildFromMappable()` (l'option `includeDescriptions` n'est plus nécessaire)
- Amélioration du parsing `@property` pour gérer les descriptions multi-lignes et les cas complexes
- Priorité des descriptions : `@var` sur les propriétés individuelles > `@property` dans le PHPDoc de la classe
- Nettoyage automatique des caractères de fin de commentaire (`*/`, `/`) dans les descriptions extraites

### Fixed
- Correction du parsing des descriptions pour éviter d'inclure les caractères de fin de commentaire PHPDoc

## [0.14.0] - 2026-01-18

### Added
- **Intégration complète des schémas Mappable dans OpenAPI** (Phase 2) :
  - `OpenAPIOperationBuilder` utilise maintenant `OpenAPISchemaBuilder` pour générer les schémas de réponse
  - Les routes avec `returnType` dans `RouteAttribute` génèrent automatiquement des schémas détaillés
  - Support des tableaux de Mappable (`User[]`) avec schéma OpenAPI `array` et `items.$ref`
  - Support des réponses multiples par code HTTP (`['200' => 'User', '404' => 'Error']`)
  - Génération automatique de la section `components/schemas` dans la spécification OpenAPI
  - Les schémas sont partagés entre les routes (réutilisation via `$ref`)
- `OpenAPIOperationBuilder` accepte maintenant `OpenAPISchemaBuilder` en dépendance
- Nouvelle méthode `OpenAPIOperationBuilder::getSchemaBuilder()` pour accéder au builder
- Tests d'intégration pour valider le flux complet route → schéma OpenAPI

### Changed
- `OpenAPIGenerator` ajoute automatiquement `components/schemas` si des schémas Mappable sont utilisés
- `OpenAPIOperationBuilder::getDefaultResponses()` utilise le `returnType` pour générer les schémas
- Amélioration du cache de `OpenAPISchemaBuilder` pour supporter plusieurs instances

### Fixed
- Correction du cache statique de `OpenAPISchemaBuilder` qui ne remplissait pas `componentsSchemas` sur les instances ultérieures

## [0.13.0] - 2026-01-18

### Added
- Nouvelle classe `OpenAPISchemaBuilder` pour générer des schémas OpenAPI depuis les classes PHP
  - Support des types primitifs (int, float, bool, string)
  - Support de DateTime avec format `date-time`
  - Support des tableaux avec notation `Type[]`
  - Support des classes `Mappable` avec génération récursive de schémas
  - Mise en cache des schémas générés pour optimiser les performances
  - Génération de références `$ref` vers `components/schemas`
  - Extraction des descriptions depuis PHPDoc (`@property`)
- Nouveau paramètre `returnType` dans `RouteAttribute` pour spécifier le type de retour d'une route
  - Peut être une string (`'User'` ou `User::class`)
  - Peut être un tableau pour un type de tableau (`'User[]'`)
  - Peut être un array pour les réponses multiples (`['200' => 'User', '404' => 'Error']`)
- Extraction du type de retour depuis PHPDoc `@return` dans `ControllerMetadataExtractor`
  - Support de `@return User`, `@return User[]`, `@return HTTPResponse<User>`

### Changed
- `ControllerMetadataExtractor::extract()` retourne maintenant une clé `returnType` dans les métadonnées
- Les métadonnées extraites incluent le fallback vers PHPDoc si `returnType` n'est pas défini dans `RouteAttribute`

## [0.12.0] - 2026-01-18

### Added
- Support de `RouteAttribute` sur les classes en plus des méthodes
- Fusion automatique des métadonnées : les valeurs de la classe sont utilisées par défaut, surchargées par les valeurs de la méthode
- Les tags de la classe et de la méthode sont fusionnés (union des tableaux)
- Utile pour tagger une classe entière tout en définissant les méthodes individuellement

### Changed
- `RouteAttribute` peut maintenant être appliqué sur les classes (`Attribute::TARGET_CLASS`)
- `ControllerMetadataExtractor` extrait et fusionne les attributs de la classe et de la méthode
- Les valeurs de la méthode ont priorité sur celles de la classe (sauf pour les tags qui sont fusionnés)

## [0.11.0] - 2026-01-18

### Added
- Nouvelle classe `RouteAttribute` : attribut PHP 8 pour définir les métadonnées des routes API
- Support des propriétés OpenAPI via `RouteAttribute` :
  - `name` : résumé/summary de l'opération
  - `description` : description détaillée
  - `contentType` : type de contenu de la réponse (JSON, HTML, XML, etc.)
  - `tags` : tags pour grouper les opérations dans OpenAPI
  - `deprecated` : marquer une opération comme dépréciée
  - `operationId` : identifiant unique personnalisé
- Les attributs `RouteAttribute` ont priorité sur les PHPDoc lors de la génération OpenAPI
- Support du type de contenu dans les réponses OpenAPI générées
- Nouveaux tests unitaires pour `RouteAttribute` dans la génération OpenAPI

### Changed
- `ControllerMetadataExtractor` extrait maintenant les attributs `RouteAttribute` en priorité sur les PHPDoc
- `OpenAPIOperationBuilder` utilise les nouvelles métadonnées (tags, deprecated, operationId, contentType)

## [0.10.0] - 2026-01-18

### Added
- Support des callables (closures et callable arrays) en plus des classes pour les handlers de routes
- Les méthodes `get()`, `post()` et `setRoutes()` acceptent maintenant `string|\Closure|array` comme handler
- Extraction automatique des métadonnées PHPDoc depuis les closures et méthodes de classe pour OpenAPI
- Nouveaux tests unitaires pour valider le support des callables

### Changed
- **BREAKING**: Renommage de la propriété `class` en `handler` dans `RouteConfig`
- Modification de `ControllerMetadataExtractor` pour supporter l'extraction de PHPDoc depuis les closures et callable arrays
- Mise à jour de `OpenAPIGenerator` pour utiliser le nouveau nom de propriété `handler`

## [0.9.0] - 2026-01-18

### Added
- Ajout de la génération automatique de spécifications OpenAPI 3.0.3 depuis les routes enregistrées
- Nouvelle méthode `generateOpenAPISpec()` dans la classe `API` pour générer la documentation OpenAPI
- Création de classes spécialisées pour la génération OpenAPI :
  - `OpenAPIGenerator` : orchestrateur principal de la génération
  - `OpenAPIPathConverter` : conversion des patterns regex en chemins OpenAPI avec paramètres
  - `ControllerMetadataExtractor` : extraction des métadonnées depuis les contrôleurs (PHPDoc)
  - `OpenAPIOperationBuilder` : construction des opérations OpenAPI
- Support de la conversion automatique des paramètres regex (ex: `(\d+)` → `{id}` avec type `integer`)
- Génération automatique des `operationId`, `requestBody` pour POST/PUT/PATCH, et réponses par défaut
- Ajout de tests unitaires complets pour toutes les nouvelles classes

### Changed
- Refactorisation de la classe `API` pour séparer les responsabilités (principe SRP)
- La génération OpenAPI est maintenant modulaire et extensible

## [0.8.0] - 2026-01-17

### Added
- Ajout des méthodes `getUniqueKey()` et `getIndex()` dans l'interface `SQLHelper` pour gérer les clés uniques et les index sur plusieurs colonnes
- Ajout de la méthode `createIndex()` dans l'interface `SQLHelper` pour créer des index séparément (nécessaire pour SQLite)
- Implémentation des méthodes de gestion des clés dans `SQLiteHelper` et `MySQLHelper`
- Support des clés multiples (ex: unique sur "title" et "author")
- Ajout de tests unitaires pour valider les nouvelles fonctionnalités de gestion des clés et index

### Changed
- `getIndex()` pour SQLite retourne maintenant une exception car SQLite ne supporte pas INDEX dans CREATE TABLE (utiliser `createIndex()` à la place)

## [0.7.0] - 2026-01-17 ([`0ef531a`][0.7.0])

### Changed
- Refactorisation du routage et de la gestion des conditions avec l'introduction des classes `RouteConfig` et `SQLCondition`
- Mise à jour de la documentation et des tests pour refléter ces changements
- Amélioration de la cohérence dans la configuration des routes et la représentation des conditions SQL

## [0.6.0] - 2026-01-17 ([`1f1463a`][0.6.0])

### Added
- Ajout des classes `Registries` et `Request` avec documentation complète
- Implémentation du lazy loading et des patterns singleton pour la gestion des registres
- Amélioration de la classe `Request` pour la gestion HTTP avec parsing automatique
- Ajout de tests unitaires pour la fonctionnalité Registries

## [0.5.0] - 2026-01-17 ([`ca170c0`][0.5.0])

### Changed
- Refactorisation de `Registries` pour supporter l'initialisation par défaut de la base de données
- Amélioration de la gestion des instances
- Mise à jour du README.md avec les instructions d'initialisation et des exemples
- Modification des tests pour refléter les changements dans l'utilisation des registres sans paramètres de base de données explicites

### Fixed
- Correction de l'initialisation de `requestTime` dans la classe `Request` pour assurer le bon type casting de la variable `REQUEST_TIME`

## [0.4.1] - 2026-01-16 ([`15839b4`][0.4.1])

### Fixed
- Correction de l'initialisation de `requestTime` dans la classe `Request` pour assurer le bon type casting de la variable `REQUEST_TIME`

## [0.4.0] - 2026-01-16 ([`7ff29a9`][0.4.0])

### Added
- Ajout de la classe `PIWSRouter` pour le support du serveur web intégré PHP
- Mise à jour du README.md avec des notes de développement pour la configuration et les tests HTTP

### Changed
- Amélioration du README.md avec des exemples détaillés pour MySQL, SQLite et NativeConnector
- Mise à jour de la section API pour inclure des exemples d'enregistrement de routes fluides et de gestion des requêtes

## [0.3.1] - 2026-01-16 ([`afcdfb1`][0.3.1])

### Changed
- Amélioration de la documentation avec des exemples détaillés pour MySQL, SQLite et NativeConnector
- Mise à jour de la section API pour inclure des exemples d'enregistrement de routes fluides et de gestion des requêtes

## [0.3.0] - 2026-01-16 ([`57a233f`][0.3.0])

### Changed
- **BREAKING**: Refactorisation des méthodes de routage de l'API pour accepter `pattern` et `class` comme paramètres au lieu d'objets `RouteMenu`
- Mise à jour des tests pour refléter les nouvelles signatures de méthodes
- Amélioration de la lisibilité du code

## [0.2.1] - 2026-01-16 ([`ec09f4e..b18bffa`][0.2.1])

### Changed
- Refactorisation de `MySQLTest` pour charger les variables d'environnement dynamiquement
- Mise à jour de `sample.env` pour utiliser `127.0.0.1` pour `MYSQL_TEST_HOST`
- Amélioration de la gestion des connexions à la base de données dans les tests

### Fixed
- Correction de la configuration Composer pour CI/PHP
- Ajout de la création de répertoires dans le workflow CI pour le cache et les résultats
- Ajout de la création du répertoire de couverture dans le workflow CI

### Changed
- Mise à jour des scripts PHPUnit dans `composer.json` pour désactiver la couverture par défaut
- Ajout d'une commande de couverture séparée
- Suppression de la configuration de couverture de `phpunit.xml` pour simplifier la configuration des tests

## [0.2.0] - 2026-01-16 ([`9dabc88`][0.2.0])

### Changed
- Refactorisation du routage de l'API et amélioration de la structure `RouteMenu`
- Mise à jour de `composer.json` pour refléter le nouveau nom de package et la version
- Introduction d'une interface fluide pour l'enregistrement de routes dans la classe `API`
- Amélioration de la logique de validation des tokens pour gérer les payloads null
- Mise à jour des tests pour s'aligner sur la nouvelle instanciation de `RouteMenu`

### Added
- Amélioration de la configuration des tests en ajoutant des scripts dans `composer.json` pour exécuter les tests PHPUnit
- Suppression du Makefile obsolète
- Introduction d'un workflow GitHub Actions pour les tests automatisés avec le service MySQL
- Mise à jour de `MySQLTest` pour utiliser les variables d'environnement pour la configuration de la connexion à la base de données

## [0.1.0] - 2026-01-16 ([`74ffed1`][0.1.0])

### Added
- Initialisation de la structure du projet avec les fichiers essentiels
- Configuration `.gitignore` et `composer.json`
- Classes de base pour l'API, la gestion de base de données et la gestion des réponses HTTP
- Configuration d'environnement d'exemple
- Cas de test pour les fonctionnalités principales

[0.7.0]: https://github.com/tivins/webback/commit/0ef531a
[0.6.0]: https://github.com/tivins/webback/commit/1f1463a
[0.5.0]: https://github.com/tivins/webback/commit/ca170c0
[0.4.1]: https://github.com/tivins/webback/commit/15839b4
[0.4.0]: https://github.com/tivins/webback/commit/7ff29a9
[0.3.1]: https://github.com/tivins/webback/commit/afcdfb1
[0.3.0]: https://github.com/tivins/webback/commit/57a233f
[0.2.1]: https://github.com/tivins/webback/compare/ec09f4e..b18bffa
[0.2.0]: https://github.com/tivins/webback/commit/9dabc88
[0.1.0]: https://github.com/tivins/webback/commit/74ffed1
