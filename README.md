# Budget App

Application interne de gestion budgétaire : suivi des budgets et lignes budgétaires par département, workflow complet d'achat (bons de commande → factures), gestion des fournisseurs et administration fine des droits utilisateurs.

## Sommaire

- [Aperçu fonctionnel](#aperçu-fonctionnel)
- [Stack technique](#stack-technique)
- [Architecture du dépôt](#architecture-du-dépôt)
- [Démarrage rapide](#démarrage-rapide)
- [Déploiement en production](#déploiement-en-production)
- [Commandes d'exploitation](#commandes-dexploitation)
- [Sécurité et secrets](#sécurité-et-secrets)
- [Support](#support)

## Aperçu fonctionnel

- **Budgets** : création, suivi de consommation, clôture. Types fonctionnement / investissement, rattachement à un ou plusieurs départements.
- **Lignes budgétaires** : rattachées à un budget, une catégorie/sous-catégorie et un département ; gestion des annuités (amortissement sur plusieurs exercices) avec 3 modes de gestion du dépassement (blocage, surplus assumé, report sur l'annuité suivante).
- **Bons de commande** : workflow à 6 statuts (brouillon → envoyé → réception → validation → paiement → réglée), validation/invalidation soumise à permission, répartition en tranches.
- **Factures** : rattachées à un bon de commande, workflow à 4 statuts (réception → validation → paiement → réglée), types acompte/finale.
- **Documents justificatifs** : pièces jointes aux factures (scans, justificatifs), 5 fichiers maximum et 10 Mo par fichier.
- **Fournisseurs** : fiche fournisseur avec historique de score annuel.
- **Référentiels** : départements, catégories, sous-catégories, statuts personnalisés (par-dessus les statuts figés du workflow).
- **Administration** : utilisateurs, permissions unitaires très granulaires, packs de permissions applicables en un clic. Aucune auto-inscription : tous les comptes sont créés par un administrateur.

## Stack technique

| Composant | Détail |
|---|---|
| Backend | Laravel 13, PHP 8.3, authentification par jetons Laravel Sanctum |
| Base de données | PostgreSQL 18 |
| Frontend | React 19, Vite, Tailwind CSS 4, React Router |
| Tests | PHPUnit (backend), Vitest + Testing Library (frontend) |
| Conteneurisation | Docker Compose (développement et production) |

## Architecture du dépôt

| Dossier / fichier | Contenu |
|---|---|
| `Backend/` | API Laravel — voir [Backend/README.md](Backend/README.md) |
| `Frontend/` | Interface React/Vite — voir [Frontend/README.md](Frontend/README.md) |
| `Backend/compose.yaml` | Environnement Docker de développement (API + Vite + PostgreSQL) |
| `compose.production.yaml` | Orchestration Docker de production (API PHP-FPM + Nginx + PostgreSQL) |

## Démarrage rapide

La mise en route détaillée (prérequis, variables d'environnement, première exécution) est documentée dans chaque module :

1. [Backend/README.md](Backend/README.md#développement-local) — API et base de données
2. [Frontend/README.md](Frontend/README.md#démarrage) — interface web

## Déploiement en production

Procédure complète — configuration des secrets, build des images, migrations, TLS — dans [Backend/README.md](Backend/README.md#déploiement-docker).

## Commandes d'exploitation

Ces commandes Artisan s'exécutent à l'intérieur du conteneur backend (`docker compose ... exec backend php artisan ...` en production, ou directement en local). Détails dans [Backend/README.md](Backend/README.md#commandes-dexploitation).

| Commande | Usage |
|---|---|
| `app:create-admin` | Crée le tout premier compte administrateur. **Indispensable après un déploiement initial** : sans elle, personne ne peut se connecter. |
| `db:backup` | Sauvegarde toutes les données applicatives dans un fichier JSON. |
| `db:restore [fichier]` | Restaure une sauvegarde créée par `db:backup`. |
| `demo:unseed` | Supprime les données de démonstration sans toucher aux données réelles. |

## Sécurité et secrets

- Ne jamais committer de secret : `Backend/.env` (local) et `Backend/.env.production` (serveur) sont ignorés par Git.
- Les fichiers `.env.example` et `.env.production.example` documentent uniquement les variables requises, sans valeur sensible.
- `APP_DEBUG` doit impérativement être `false` en production.
- Les comptes et données de démonstration (seeder) ne sont créés qu'en environnement `local`/`testing` et n'ont aucun effet en production.
- Le fichier `database/seeders/DatabaseSeeder.php` contient des mots de passe de démonstration en clair : sans risque en production (le seeder n'y tourne jamais), mais à garder en tête pour tout audit du code source.

## Support

Pour toute question technique liée au déploiement ou à la maintenance, se référer aux README de chaque module