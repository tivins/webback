# Changelog

Tous les changements notables de ce projet seront documentés dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

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
