        /*
        | Comptes Cloudron (addon oidc) — ajouté par le paquet fleetbase-cloudron.
        | Les variables CLOUDRON_OIDC_* sont injectées par la plateforme quand
        | l'application est installée avec l'authentification Cloudron ; sans elles
        | le fournisseur n'est pas configuré et la console ne l'affiche pas.
        */
        'cloudron' => [
            'driver'                 => Fleetbase\Auth\OAuth\Drivers\CloudronDriver::class,
            'enabled'                => env('OAUTH_CLOUDRON_ENABLED', (bool) env('CLOUDRON_OIDC_CLIENT_ID')),
            'label'                  => env('CLOUDRON_OIDC_PROVIDER_NAME', 'Cloudron'),
            'client_id'              => env('CLOUDRON_OIDC_CLIENT_ID'),
            'client_secret'          => env('CLOUDRON_OIDC_CLIENT_SECRET'),
            'authorization_endpoint' => env('CLOUDRON_OIDC_AUTH_ENDPOINT'),
            'token_endpoint'         => env('CLOUDRON_OIDC_TOKEN_ENDPOINT'),
            'userinfo_endpoint'      => env('CLOUDRON_OIDC_PROFILE_ENDPOINT'),
        ],

