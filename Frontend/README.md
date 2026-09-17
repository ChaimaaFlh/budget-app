# Frontend Budget App

Interface web React 19 / Vite du projet Budget App : budgets, lignes budgétaires, bons de commande, factures, fournisseurs et administration des utilisateurs. Style avec Tailwind CSS 4.

## Sommaire

- [Prérequis](#prérequis)
- [Démarrage](#démarrage)
- [Variable d'environnement](#variable-denvironnement)
- [Tests et build](#tests-et-build)
- [Dépannage](#dépannage)

## Prérequis

- Node.js 20+ et npm
- L'API backend démarrée (voir [Backend/README.md](../Backend/README.md)) — le frontend seul ne fonctionne pas sans elle

## Démarrage

```sh
cp .env.example .env
npm ci
npm run dev
```

L'application est disponible sur `http://localhost:5173`.

> En développement avec `docker compose` (voir [Backend/README.md](../Backend/README.md#développement-local)), le serveur Vite est déjà démarré automatiquement par le conteneur `node` — cette procédure manuelle sert pour un développement du frontend hors Docker.

## Variable d'environnement

```env
VITE_API_URL=http://127.0.0.1:8000/api
```

- `VITE_API_URL` est injectée au moment de `npm run build` (variable de build, pas d'exécution).
- En production, avec le Nginx fourni, utiliser `/api` : l'API est alors servie sous le même domaine que le frontend, ce qui évite tout problème CORS.
- Ne jamais placer de secret dans une variable `VITE_*` : elle est intégrée en clair dans le bundle JavaScript et visible côté navigateur.
- Le fichier `.env` est ignoré par Git ; seul `.env.example` doit rester versionné.

## Tests et build

```sh
npm run test      # tests Vitest + Testing Library
npm run lint       # analyse statique (oxlint)
npm run build      # build de production dans dist/
npm run preview    # prévisualiser le build de production en local
```

L'image de production est définie dans [`Dockerfile.production`](Dockerfile.production) (build multi-étage : compilation Vite puis service par Nginx) et construite par [`compose.production.yaml`](../compose.production.yaml) à la racine du dépôt. La configuration Nginx associée ([`nginx/default.conf`](nginx/default.conf)) route `/api/` vers le backend PHP-FPM et sert le reste comme une application monopage (fallback sur `index.html`).

## Dépannage

| Symptôme | Cause probable | Solution |
|---|---|---|
| Page blanche ou erreurs réseau dans la console | `VITE_API_URL` incorrecte ou API non démarrée | Vérifier `.env` et que le backend répond sur l'URL configurée |
| Erreur CORS dans la console navigateur | Origine du frontend absente de `FRONTEND_URLS` côté backend | Voir [Backend/README.md](../Backend/README.md#variables-denvironnement) |
| `npm run build` échoue en production avec une variable manquante | `VITE_API_URL` non passée au build Docker | Vérifier l'argument `VITE_API_URL` dans `compose.production.yaml` |