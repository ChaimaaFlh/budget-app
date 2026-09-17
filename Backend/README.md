# API Budget App — Backend

API REST Laravel 13 (PHP 8.3) du projet Budget App. Authentification par jetons Sanctum, base de données PostgreSQL, système de permissions granulaires.

## Sommaire

- [Prérequis](#prérequis)
- [Développement local](#développement-local)
- [Variables d'environnement](#variables-denvironnement)
- [Commandes d'exploitation](#commandes-dexploitation)
- [Données de démonstration](#données-de-démonstration)
- [Tests et vérification](#tests-et-vérification)
- [Déploiement Docker](#déploiement-docker)
- [Dépannage](#dépannage)

## Prérequis

- Docker Desktop (ou Docker Engine + Docker Compose) — méthode recommandée, décrite ci-dessous, **ou**
- PHP 8.3+, Composer et PostgreSQL installés en local

> Le conteneur Docker fournit PHP 8.3. Une installation locale type XAMPP en PHP 8.0 ne peut pas exécuter ce projet.

## Développement local

L'environnement de développement (`Backend/compose.yaml`) démarre trois services : l'API Laravel (`laravel`), le serveur de développement Vite du frontend (`node`) et PostgreSQL (`pgsql`). Le dossier `Backend/` est monté en volume : toute modification du code est prise en compte immédiatement, sans reconstruire l'image.

1. **Configuration** :

```sh
   cd Backend
   cp .env.example .env
```

   Renseigner au minimum `DB_PASSWORD` dans `.env`.

2. **Démarrer les conteneurs** :

```sh
   docker compose up -d --build
```

3. **Première exécution uniquement** — le dossier `vendor/` n'est pas versionné et n'est pas installé automatiquement au démarrage du conteneur ; il faut l'installer et initialiser la base une première fois :

```sh
   docker compose exec laravel composer install
   docker compose exec laravel php artisan key:generate
   docker compose exec laravel php artisan migrate
```

   Optionnel, pour disposer de données de test (voir [Données de démonstration](#données-de-démonstration)) :

```sh
   docker compose exec laravel php artisan db:seed
```

4. **Accès** :
   - API : `http://localhost:8000`
   - Frontend (servi par le conteneur `node`) : `http://localhost:5173`
   - PostgreSQL : `localhost:5432` (port exposé pour un client SQL local, ex. TablePlus, DBeaver)

Les commandes Artisan et Composer suivantes s'exécutent toutes à l'intérieur du conteneur `laravel` :

```sh
docker compose exec laravel php artisan <commande>
docker compose exec laravel composer <commande>
```

## Variables d'environnement

Référence complète dans [`.env.example`](.env.example) (développement) et [`.env.production.example`](.env.production.example) (production). Variables les plus sensibles :

| Variable | Rôle |
|---|---|
| `APP_KEY` | Secret Laravel propre à chaque environnement. Généré avec `php artisan key:generate`. Ne jamais le committer ni le régénérer sur un environnement déjà en service (ça invaliderait les sessions et données chiffrées existantes). |
| `APP_DEBUG` | Doit être `false` en production : à `true`, les erreurs exposent la configuration et la structure interne de l'application. |
| `DB_PASSWORD` | Secret PostgreSQL. Ne jamais conserver une valeur d'exemple en production. |
| `FRONTEND_URLS` | Origines CORS autorisées, séparées par des virgules (ex. `https://app.example.com`). Doit correspondre exactement au(x) domaine(s) du frontend en production. |
| `SESSION_ENCRYPT` | À `true` en production (déjà positionné dans `.env.production.example`). |
| `DEMO_DEFAULT_PASSWORD` / `DEMO_ADMIN_PASSWORD` | Mots de passe des comptes de démonstration — à définir uniquement en local, ignorés en production. |

## Commandes d'exploitation

Ces commandes sont propres à ce projet (en plus des commandes Artisan standards de Laravel).

### `app:create-admin` — créer le premier administrateur

**Étape obligatoire après tout déploiement initial.** Il n'existe aucune route d'inscription publique ; sans cette commande, aucun compte n'existe et personne ne peut se connecter.

```sh
php artisan app:create-admin
```

La commande demande interactivement le nom, l'e-mail et le mot de passe (12 caractères minimum), attribue toutes les permissions existantes, et force le changement de mot de passe à la première connexion. Par sécurité, elle refuse de s'exécuter si des utilisateurs existent déjà, sauf avec l'option `--force` :

```sh
php artisan app:create-admin --force
```

### `db:backup` — sauvegarder les données

Exporte toutes les tables métier (hors tables techniques Laravel : sessions, cache, jobs, migrations) dans un fichier JSON sous `storage/app/backups`.

```sh
php artisan db:backup
php artisan db:backup --name=avant-migration-2026-08
```

À utiliser avant toute opération risquée (montée de version majeure, `migrate:fresh`, etc.).

### `db:restore` — restaurer une sauvegarde

Vide puis réinjecte les données d'un fichier créé par `db:backup`. Demande confirmation sauf avec `--force`.

```sh
php artisan db:restore                       # restaure la sauvegarde la plus récente
php artisan db:restore avant-migration-2026-08
php artisan db:restore avant-migration-2026-08 --force
```

### `demo:unseed` — retirer les données de démonstration

Supprime uniquement les données créées par le seeder de démonstration (utilisateurs, fournisseurs, budgets, lignes, bons de commande, factures identifiés par leurs clés métier), sans toucher aux données créées manuellement dans l'application. Réservée aux environnements `local`/`testing` (utiliser `--force` pour outrepasser, par exemple pour nettoyer un environnement de recette).

```sh
php artisan demo:unseed
```

## Données de démonstration

`php artisan db:seed` alimente une base de démonstration complète (utilisateurs, départements, fournisseurs avec historique de score, budgets sur deux exercices, lignes budgétaires avec annuités, bons de commande couvrant les 6 statuts du workflow et les 3 modes de gestion de dépassement, factures, documents joints, statuts personnalisés). Il ne s'exécute que dans les environnements `local` et `testing` et est rejouable sans erreur (idempotent).

Avant de l'exécuter localement, définir `DEMO_DEFAULT_PASSWORD` et `DEMO_ADMIN_PASSWORD` dans `.env`.

> Ne jamais exécuter `migrate:fresh --seed` en production — `migrate:fresh` supprime toutes les tables existantes.

## Tests et vérification

```sh
php artisan test           # suite de tests PHPUnit
php artisan route:list     # inspecter les routes déclarées
php artisan optimize:clear # vider tous les caches (config, routes, vues, événements)
```

## Déploiement Docker

La configuration [`compose.production.yaml`](../compose.production.yaml) est distincte du `compose.yaml` de développement : elle sert le frontend via Nginx et l'API via PHP-FPM (sans exposer PostgreSQL à l'extérieur), avec des images construites à partir de `Dockerfile.production`.

1. **Configuration** — copier `.env.production.example` vers `.env.production` et renseigner toutes les valeurs vides avec le gestionnaire de secrets de l'hébergeur.

2. **Clé d'application** — générer une clé puis la reporter dans `APP_KEY` de `.env.production` (le conteneur refuse de démarrer si `APP_KEY` est vide) :

```sh
   docker compose --env-file Backend/.env.production -f compose.production.yaml run --rm --entrypoint php backend artisan key:generate --show
```

3. **Build et démarrage** :

```sh
   docker compose --env-file Backend/.env.production -f compose.production.yaml up -d --build
   docker compose --env-file Backend/.env.production -f compose.production.yaml exec backend php artisan migrate --force
```

4. **Créer le premier administrateur** (obligatoire, voir [ci-dessus](#app-create-admin--créer-le-premier-administrateur)) :

```sh
   docker compose --env-file Backend/.env.production -f compose.production.yaml exec backend php artisan app:create-admin
```

### Responsabilités de l'équipe de déploiement

- Terminer TLS/HTTPS devant le port 80 (proxy de l'hébergeur ou Nginx externe) — le conteneur `web` ne sert que du HTTP en clair sur le port 80.
- Gérer les secrets via le gestionnaire de secrets de l'hébergeur, jamais en clair dans le dépôt.
- Planifier des sauvegardes régulières (`db:backup`, ou sauvegarde native PostgreSQL) et tester leur restauration.
- Renouveler le certificat TLS.
- Surveiller l'espace disque du volume `laravel_storage` (fichiers joints aux factures).

## Dépannage

| Symptôme | Cause probable | Solution |
|---|---|---|
| `docker compose up` démarre mais l'API renvoie une erreur 500 | `vendor/` non installé ou `APP_KEY` manquant | Exécuter les [étapes de première exécution](#développement-local) |
| Erreur CORS dans la console navigateur | `FRONTEND_URLS` ne correspond pas à l'origine du frontend | Vérifier/ajuster `FRONTEND_URLS` dans `.env` (local) ou `.env.production` |
| Le conteneur de production ne démarre pas | `APP_KEY` vide dans `.env.production` | Générer et renseigner `APP_KEY` (voir [Déploiement Docker](#déploiement-docker)) |
| Impossible de se connecter après un déploiement initial | Aucun compte utilisateur n'existe encore | Exécuter `php artisan app:create-admin` |