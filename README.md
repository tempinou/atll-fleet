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

Addons : `mysql` (multipleDatabases — Fleetbase utilise `…fleetbase` et `…fleetbase_sandbox`), `redis`, `sendmail`, `localstorage`, `scheduler`, `oidc` (optionnel).

## Connexion avec les comptes Cloudron (OIDC)

Fleetbase ne livre que Google, Microsoft, GitHub et Apple comme fournisseurs OAuth, mais son architecture permet d'en ajouter un sans toucher à la console. Le dossier [`oauth/`](oauth/) contient un driver `cloudron` (classe Socialite OIDC générique + driver Fleetbase) copié dans `vendor/fleetbase/core-api` à la construction, et une entrée `providers.cloudron` injectée dans `config/oauth.php`, alimentée par les variables `CLOUDRON_OIDC_*` de l'addon. Callback enregistré auprès de Cloudron : `/int/v1/auth/oauth/cloudron/callback`.

Comportement côté Fleetbase (réglable dans *Admin → OAuth settings*) : un compte Cloudron dont l'e-mail correspond à un utilisateur Fleetbase existant à e-mail vérifié est relié automatiquement (`auto_link`) ; sinon, l'assistant d'inscription s'ouvre (`allow_registration`).

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
- Le SSO Cloudron passe par le mécanisme OAuth de Fleetbase (bouton « Continuer avec Cloudron ») : les comptes Fleetbase restent gérés dans Fleetbase (rôles, organisations), Cloudron ne sert qu'à s'authentifier.
- Le bundle de la console est patché à la construction (port WebSocket 443, config runtime activée) ; le build échoue si une future version change ces motifs — à adapter alors.
