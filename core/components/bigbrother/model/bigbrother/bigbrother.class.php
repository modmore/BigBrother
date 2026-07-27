<?php

use Google\Auth\OAuth2;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

/**
 * Service class for Big Brother v2.
 *
 * @author Stephane Boulard <lossendae@gmail.com>
 * @author Mark Hamstra <mark@modmore.com>
 * @author Murray Wood <murray@digitalpenguin.hk>
 * @package bigbrother
 */
class BigBrother
{
    /**
     *  A reference to the modX object.
     *
     * @var modX
     */
    public $modx;

    /**
     * Configuration options loaded from system settings
     *
     * @var array
     */
    public $config = [];

    /**
     * The cache key to use for the access token.
     * @var string
     */
    public static $accessTokenCacheKey = 'ga4_access_token';

    /**
     * Custom cache options to make sure data is cached to a custom cache partition instead of default.
     *
     * @var array
     */
    public static $cacheOptions = [
        xPDO::OPT_CACHE_KEY => 'bigbrother',
    ];

    /**
     * The version string, used for cache busting and should be increased with each release.
     *
     * @var string
     */
    public $version = '3.1.0-pl';

    /**
     * An instance of the Google Cloud PHP SDK's OAuth2 object. Used to pass into various Client as `credentials`.
     *
     * @see BigBrother::getOAuth2()
     * @var \Google\Auth\OAuth2
     */
    protected $OAuth2;

    /**
     * Constructor to load the config as needed.
     *
     * @param modX &$modx The modX object
     * @param array $config Optionally additional config properties that override
     * behaviour.
     */
    public function __construct(modX $modx, array $config = [])
    {
        $this->modx =& $modx;

        $core = $this->modx->getOption('bigbrother.core_path', $config, $this->modx->getOption('core_path') . 'components/bigbrother/');
        $assetsUrl = $this->modx->getOption('bigbrother.assets_url', $config, $this->modx->getOption('assets_url') . 'components/bigbrother/');

        $this->config = array_merge([
            'core_path' => $core,
            'model_path' => $core . 'model/',
            'processors_path' => $core . 'processors/',
            'controllers_path' => $core . 'controllers/',
            'templates_path' => $core . 'templates/',
            'chunks_path' => $core . 'elements/chunks/',
            'assets_url' => $assetsUrl,
            'connector_url' => $assetsUrl . 'connector.php',
            'authorize_url' => MODX_MANAGER_URL . '?namespace=bigbrother&a=authorize'
        ], $config);

        if ($this->modx->lexicon) {
            $this->modx->lexicon->load('bigbrother:default');
        }
    }

    /**
     * Returns an initialised instance of the Google SDK OAuth2 object.
     *
     * This will be filled with the client_id, client_secret, and necessary URIs for scopes and authorizations.
     * If a refresh token is available (system setting), that's also loaded.
     * If an access token is available (cache), that's also loaded. If not, but a refresh token is set, then it will
     * fetch a new access token automatically and save that.
     *
     * @return OAuth2
     * @throws Exception Fails if Guzzle is not available.
     */
    public function getOAuth2(bool $forceWebappFlow = false): OAuth2
    {
        // On MODX3 (alpha4 or up), grab the core-provided Client and pass that into the Google instance.
        if ($this->modx->services instanceof ContainerInterface) {
            try {
                $client = $this->modx->services->get(ClientInterface::class);
                if ($client instanceof \GuzzleHttp\Client) {
                    \Google\Auth\HttpHandler\HttpClientCache::setHttpClient($client);
                }
            } catch (Exception $e) {
                // ignore as we're likely on alpha3 or before
            }
        }

        // Make sure that by now we do have a guzzle client instance, otherwise fail.
        if (!class_exists(\GuzzleHttp\Client::class)) {
            throw new Exception('Failed loading Guzzle Client.');
        }

        if (!$this->OAuth2) {
            // webapp flow (3.0+) using authorization proxy
            if ($forceWebappFlow || $this->modx->getOption('bigbrother.oauth_flow') === 'webapp') {
                $clientId = $this->modx->getOption('bigbrother.oauth_client_id');
                $clientSecret = $this->modx->getOption('bigbrother.oauth_client_secret');
                $redirectUri = 'https://modmore.com/bigbrotherauth/';
            }
            // native OOB flow (<3.0) with possibly custom credentials
            // this is deprecated and will be removed from GCP by February 2023
            else {
                $clientId = $this->modx->getOption('bigbrother.native_app_client_id');
                $clientSecret = $this->modx->getOption('bigbrother.native_app_client_secret');
                $redirectUri = 'urn:ietf:wg:oauth:2.0:oob';
            }

            $config = [
                'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
                'tokenCredentialUri' => 'https://oauth2.googleapis.com/token',
                'authorizationUri' => 'https://accounts.google.com/o/oauth2/auth',
                'redirectUri' => $redirectUri,
                'clientId' => $clientId,
                'clientSecret' => $clientSecret,
            ];
            $this->OAuth2 = new OAuth2($config);
        }

        // If the scope doesn't already have the refresh token, but we do have it, set it
        if (!$this->OAuth2->getRefreshToken()) {
            $refreshToken = $this->modx->getOption('bigbrother.refresh_token');
            if (!empty($refreshToken)) {
                $this->OAuth2->setRefreshToken($refreshToken);
            }
        }

        // If the scope doesn't already have the access token...
        if (!$this->OAuth2->getAccessToken()) {
            // If we have it in cache and it's the right (array) format, set it
            $accessToken = $this->modx->cacheManager->get(self::$accessTokenCacheKey, self::$cacheOptions);
            if (is_array($accessToken)
                && array_key_exists('access_token', $accessToken)
                && !empty($accessToken['access_token'])
            ) {
                $this->OAuth2->updateToken($accessToken);
            }
            // If we don't have the access token, but we do have a refresh token, fetch a new auth token
            elseif ($this->OAuth2->getRefreshToken()) {
                try {
                    $accessToken = $this->OAuth2->fetchAuthToken();
                    $this->setAccessToken($accessToken);
                } catch (Exception $e) {
                    $this->modx->log(modX::LOG_LEVEL_ERROR, '[BigBrother] Unexpected ' . get_class($e) . ' refreshing access token: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
                }
            }
        }

        return $this->OAuth2;
    }

    public function setAccessToken(array $accessToken)
    {
        // Turn expires_in into an absolute time to avoid reading from cache not determining it's still valid
        $lifetime = $accessToken['expires_in'];
        $accessToken['expires_at'] = time() + $lifetime;
        unset($accessToken['expires_in']);

        // Save it in the cache until 1 minute before its expiration time
        $this->modx->cacheManager->set(self::$accessTokenCacheKey, $accessToken, $lifetime - 60, self::$cacheOptions);

        if (array_key_exists('refresh_token', $accessToken)
            && !empty($accessToken['refresh_token'])
            && $accessToken['refresh_token'] !== $this->modx->getOption('bigbrother.refresh_token')
        ) {
            $this->setRefreshToken($accessToken['refresh_token']);
        }
    }

    /**
     * Format metric value for front end display
     *
     * @access public
     * @param string $name The metric name
     * @param string $value The metric value
     * @return string The formatted metric value
     * @deprecated Miiiiight come in handy so keeping it for now - but consider this a prima target for refactor
     */
    public function formatValue($name, $value)
    {
        switch ($name) {
            case 'ga:avgTimeOnSite':
                $value = sprintf("%02u:%02u:%02u", $value / 3600, $value % 3600 / 60, $value % 60);
                break;
            case 'ga:percentNewVisits':
            case 'ga:visitBounceRate':
            case 'ga:exitRate':
                $value = round($value, 2) . ' %';
                break;
            case 'ga:pageviewsPerVisit':
                $value = round($value, 2);
                break;
            default:
                break;
        }

        return $value;
    }

    /**
     * Helper method to update/create a new MODx system setting
     *
     * @param string $key The setting key
     * @param mixed $value The setting value
     * @param string $type The setting type (Optional) default to textfield
     * @return void
     */
    private function updateOption($key, $value, $type = 'textfield'): void
    {
        $setting = $this->modx->getObject('modSystemSetting', array(
            'key' => 'bigbrother.' . $key,
        ));
        if (!$setting) {
            $setting = $this->modx->newObject('modSystemSetting');
            $setting->fromArray(array(
                'key' => 'bigbrother.' . $key,
                'xtype' => $type,
                'namespace' => 'bigbrother',
                'area' => 'core',
            ), '', true);
        }

        $setting->set('value', $value);
        $setting->save();

        $this->modx->getCacheManager()->refresh([
            'system_settings' => [],
        ]);
    }

    public function getPropertyID(): string
    {
        return (string)$this->modx->getOption('bigbrother.property_id');
    }

    public function setProperty(string $propertyId)
    {
        $this->updateOption('property_id', $propertyId);
    }

    public function setRefreshToken(string $refreshToken)
    {
        $this->updateOption('refresh_token', $refreshToken);
    }

    public function setOauthFlow(string $flow)
    {
        $this->updateOption('oauth_flow', $flow);
    }

    public function getAuthorizeUrl(): string
    {
        return $this->config['authorize_url'];
    }

}
