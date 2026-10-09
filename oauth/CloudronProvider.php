<?php

namespace Fleetbase\Auth\OAuth\Socialite;

use Fleetbase\Auth\OAuth\Socialite\Concerns\ServerSidePkce;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Fournisseur OpenID Connect générique, utilisé pour l'addon `oidc` de Cloudron.
 *
 * Ajouté par le paquet Cloudron (fleetbase-cloudron), sur le modèle de
 * MicrosoftProvider : Socialite ne livre pas de fournisseur OIDC générique.
 *
 * Les points de terminaison viennent de la configuration (variables
 * CLOUDRON_OIDC_*), jamais d'une découverte à la volée : la découverte ferait un
 * aller-retour réseau à chaque redirection et déplacerait la confiance vers un
 * document distant. Le profil est lu sur le point de terminaison userinfo avec le
 * jeton d'accès obtenu directement auprès du serveur de jetons (échange
 * confidentiel, PKCE + secret client) : c'est la source la plus simple et la plus
 * sûre, sans dépendre de l'algorithme de signature de l'id_token (Cloudron peut
 * signer en RS256 ou EdDSA).
 */
class CloudronProvider extends AbstractProvider implements ProviderInterface
{
    use ServerSidePkce;

    /**
     * Séparateur des scopes demandés.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * @var array<int, string>
     */
    protected $scopes = ['openid', 'profile', 'email'];

    protected string $authorizationEndpoint = '';

    protected string $tokenEndpoint = '';

    protected string $userinfoEndpoint = '';

    public function withEndpoints(string $authorization, string $token, string $userinfo): static
    {
        $this->authorizationEndpoint = $authorization;
        $this->tokenEndpoint         = $token;
        $this->userinfoEndpoint      = $userinfo;

        return $this;
    }

    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase($this->authorizationEndpoint, $state);
    }

    protected function getTokenUrl()
    {
        return $this->tokenEndpoint;
    }

    /**
     * Échange le code en authentifiant le client en HTTP Basic (client_secret_basic),
     * méthode par défaut des serveurs OIDC conformes (dont celui de Cloudron), le
     * secret n'étant alors plus envoyé dans le corps de la requête.
     *
     * @param string $code
     *
     * @return array<string, mixed>
     */
    public function getAccessTokenResponse($code)
    {
        $fields = $this->getTokenFields($code);
        unset($fields['client_secret']);

        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            RequestOptions::HEADERS     => ['Accept' => 'application/json'],
            RequestOptions::AUTH        => [$this->clientId, $this->clientSecret],
            RequestOptions::FORM_PARAMS => $fields,
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * Le profil, lu sur userinfo avec le jeton d'accès.
     *
     * @param string $token
     *
     * @return array<string, mixed>
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get($this->userinfoEndpoint, [
            RequestOptions::HEADERS => [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        $user = json_decode((string) $response->getBody(), true);

        return is_array($user) ? $user : [];
    }

    /**
     * @param array<string, mixed> $user
     */
    protected function mapUserToObject(array $user)
    {
        return (new SocialiteUser())->setRaw($user)->map([
            'id'       => Arr::get($user, 'sub'),
            'nickname' => Arr::get($user, 'preferred_username'),
            'name'     => Arr::get($user, 'name') ?: Arr::get($user, 'preferred_username'),
            'email'    => Arr::get($user, 'email'),
            'avatar'   => Arr::get($user, 'picture'),
        ]);
    }
}
