# Fleetbase pour Cloudron

Paquet communautaire [Cloudron](https://docs.cloudron.io/apps#community-app) pour [Fleetbase](https://github.com/fleetbase/fleetbase) (édition Community, AGPL-3.0).

## Installation sur un Cloudron

Dans l'App Store du tableau de bord → *Add custom app* → coller l'URL du fichier `CloudronVersions.json` de ce dépôt :

```
https://github.com/tempinou/fleetbase-cloudron/blob/main/CloudronVersions.json
```

ou en ligne de commande :

```bash
cloudron install --versions-url https://github.com/tempinou/fleetbase-cloudron/blob/main/CloudronVersions.json -l fleet.example.com
```

## Architecture du conteneur

| Processus | Rôle | Port interne |
|---|---|---|
| nginx | point d'entrée unique, sert la console, proxifie l'API et le WebSocket | 8000 (`httpPort`) |
| Octane / FrankenPHP | API Laravel | 127.0.0.1:8001 |
| `queue:work` | worker de file d'attente (Redis) | — |
| SocketCluster | temps réel | 127.0.0.1:38000 |
| scheduler Cloudron | `php artisan schedule:run` chaque minute | — |

Addons : `mysql` (multipleDatabases — Fleetbase utilise `…fleetbase` et `…fleetbase_sandbox`), `redis`, `sendmail`, `localstorage`, `scheduler`.

Données persistantes (`/app/data`) :

- `env` — `APP_KEY` et réglages de l'administrateur (priment sur l'environnement calculé)
- `storage/` — `storage/` de Laravel (uploads dans `storage/app/public`, servis sur `/storage/`)
- `fleetbase.config.json` — config runtime de la console
- `fleetbase-id` — identifiant d'instance

## Construction et publication

```bash
cloudron build --repository build.devclics.com/<user>/io.fleetbase.cloudronapp --tag 0.1.0
cloudron install -l fleet.example.com          # test
cloudron versions add --state testing          # ajoute l'image construite à CloudronVersions.json
```

L'image part des images officielles `fleetbase/fleetbase-api` et `fleetbase/fleetbase-console` de la release (pas de recompilation). Pour une nouvelle version de Fleetbase : mettre à jour les tags/digests dans le `Dockerfile`, `upstreamVersion` et `version` dans `CloudronManifest.json`, le `CHANGELOG`, rebâtir, puis `cloudron versions add`.

## Limites connues

- Installation d'extensions depuis le registre Fleetbase impossible (système de fichiers en lecture seule) ; les extensions de la release sont préinstallées.
- Pas d'intégration SSO Cloudron : Fleetbase gère ses propres comptes.
- Le bundle de la console est patché à la construction (port WebSocket 443, config runtime activée) ; le build échoue si une future version change ces motifs — à adapter alors.
