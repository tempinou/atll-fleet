<?php

namespace Fleetbase\Auth\OAuth\Drivers;

use Fleetbase\Auth\OAuth\AbstractOAuthProviderDriver;
use Fleetbase\Auth\OAuth\OAuthUserProfile;
use Fleetbase\Auth\OAuth\Socialite\CloudronProvider;
use Laravel\Socialite\Two\AbstractProvider as SocialiteProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Connexion avec les comptes Cloudron (addon `oidc`).
 *
 * Ajouté par le paquet Cloudron (fleetbase-cloudron). Les valeurs viennent des
 * variables CLOUDRON_OIDC_* injectées par la plateforme (voir config/oauth.php) ;
 * elles restent modifiables dans l'interface d'administration comme pour les
 * autres fournisseurs.
 */
class CloudronDriver extends AbstractOAuthProviderDriver
{
    public static function id(): string
    {
        return 'cloudron';
    }

    public static function label(): string
    {
        $name = config('oauth.providers.cloudron.label');

        return is_string($name) && $name !== '' ? $name : 'Cloudron';
    }

    public static function icon(): string
    {
        // Icône Font Awesome "brands" : la console affiche le logo d'un fournisseur
        // inconu via <FaIcon @prefix="fab">.
        return 'openid';
    }

    public static function configSchema(): array
    {
        return [
            'client_id' => [
                'label'    => 'Client ID',
                'required' => true,
            ],
            'client_secret' => [
                'label'    => 'Client Secret',
                'secret'   => true,
                'required' => true,
            ],
            'authorization_endpoint' => [
                'label'       => 'Authorization endpoint',
                'placeholder' => 'https://my.example.com/openid/auth',
                'required'    => true,
            ],
            'token_endpoint' => [
                'label'       => 'Token endpoint',
                'placeholder' => 'https://my.example.com/openid/token',
                'required'    => true,
            ],
            'userinfo_endpoint' => [
                'label'       => 'Userinfo endpoint',
                'placeholder' => 'https://my.example.com/openid/me',
                'required'    => true,
            ],
        ];
    }

    protected static function requiredKeys(): array
    {
        return ['client_id', 'authorization_endpoint', 'token_endpoint', 'userinfo_endpoint'];
    }

    protected function socialiteProviderClass(): string
    {
        return CloudronProvider::class;
    }

    protected function scopes(): array
    {
        return ['openid', 'profile', 'email'];
    }

    protected function configureProvider(SocialiteProvider $provider): SocialiteProvider
    {
        if ($provider instanceof CloudronProvider) {
            $provider->withEndpoints(
                $this->endpoint('authorization_endpoint'),
                $this->endpoint('token_endpoint'),
                $this->endpoint('userinfo_endpoint')
            );
        }

        return $provider;
    }

    protected function normalize(SocialiteUser $user, array $callbackPayload): OAuthUserProfile
    {
        $email = $user->getEmail();

        return new OAuthUserProfile(
            self::id(),
            (string) $user->getId(),
            $email,
            $this->emailIsVerified($user),
            $user->getName(),
            $user->getAvatar(),
            array_filter([
                'preferred_username' => $this->rawClaim($user, 'preferred_username'),
                'groups'             => $this->rawClaim($user, 'groups'),
            ], fn ($value) => $value !== null)
        );
    }

    /**
     * L'annuaire Cloudron est géré par l'administrateur de l'instance (même
     * raisonnement que le déploiement Microsoft mono-locataire) : une adresse
     * présente dans le profil est digne de confiance, et Cloudron la marque de
     * toute façon vérifiée via `email_verified` quand il envoie ce claim.
     */
    protected function emailIsVerified(SocialiteUser $user): bool
    {
        $email = $user->getEmail();

        if (!is_string($email) || $email === '') {
            return false;
        }

        $claim = $this->rawClaim($user, 'email_verified');

        if ($claim === false || $claim === 'false' || $claim === 0) {
            return false;
        }

        return true;
    }

    protected function endpoint(string $key): string
    {
        $value = $this->config->get($key);

        return is_string($value) ? $value : '';
    }
}
