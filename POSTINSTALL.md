Ouvrez l'application : l'assistant d'installation vous propose de créer votre organisation et le premier compte administrateur (à faire tout de suite — tant qu'aucun compte n'existe, n'importe qui pouvant joindre l'URL peut le créer).

<sso>
**Connexion avec les comptes Cloudron** : un bouton « Continuer avec Cloudron » est proposé sur la page de connexion. Un utilisateur Cloudron dont l'adresse e-mail correspond à un compte Fleetbase existant (e-mail vérifié) est relié automatiquement ; un utilisateur inconnu passe par l'assistant d'inscription (désactivable dans *Admin → OAuth settings*, option « allow registration »). Conseil : créez le premier administrateur avec la même adresse que votre compte Cloudron.
</sso>

Réglages optionnels (clé Google Maps, Twilio, stockage S3…) : éditez `/app/data/env` via le terminal web ou le gestionnaire de fichiers, puis redémarrez l'application.

Les fichiers uploadés sont dans `/app/data/storage/app/public`.
