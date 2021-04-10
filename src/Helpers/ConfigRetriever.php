<?php

namespace SocialiteProviders\Manager\Helpers;

use Closure;
use SocialiteProviders\Manager\Config;
use SocialiteProviders\Manager\Contracts\Helpers\ConfigRetrieverInterface;
use SocialiteProviders\Manager\Exception\MissingConfigException;

class ConfigRetriever implements ConfigRetrieverInterface
{
    /**
     * Th provider name.
     *
     * @var string
     */
    protected $providerName;

    /**
     * The provider identifier.
     *
     * @var string
     */
    protected $providerIdentifier;

    /**
     * The services array.
     *
     * @var array
     */
    protected $servicesArray;

    /**
     * The additional configuration keys.
     *
     * @var array
     */
    protected $additionalConfigKeys;

    /**
     * Create a new configuration object.
     *
     * @param  string  $providerName
     * @param  array  $additionalConfigKeys
     * @return \SocialiteProviders\Manager\Config
     */
    public function fromServices($providerName, array $additionalConfigKeys = [])
    {
        $this->providerName = $providerName;
        $this->getConfigFromServicesArray($providerName);

        $this->additionalConfigKeys = $additionalConfigKeys = array_unique($additionalConfigKeys + ['guzzle']);

        return new Config(
            $this->getFromServices('client_id'),
            $this->getFromServices('client_secret'),
            $this->getFromServices('redirect'),
            $this->getConfigItems($additionalConfigKeys, function ($key) {
                return $this->getFromServices(strtolower($key));
            })
        );
    }

    /**
     * Get the configuration items.
     *
     * @param  array  $configKeys
     * @param  \Closure  $keyRetrievalClosure
     * @return array
     */
    protected function getConfigItems(array $configKeys, Closure $keyRetrievalClosure)
    {
        return $this->retrieveItemsFromConfig($configKeys, $keyRetrievalClosure);
    }

    /**
     * Retrieve the items from the configuration.
     *
     * @param  array  $keys
     * @param  \Closure  $keyRetrievalClosure
     * @return array
     */
    protected function retrieveItemsFromConfig(array $keys, Closure $keyRetrievalClosure)
    {
        $items = [];

        foreach ($keys as $key) {
            $items[$key] = $keyRetrievalClosure($key);
        }

        return $items;
    }

    /**
     * Get a configuration value for a key.
     *
     * @param  string  $key
     * @return array|mixed|null
     * @throws \SocialiteProviders\Manager\Exception\MissingConfigException
     */
    protected function getFromServices($key)
    {
        if (array_key_exists($key, $this->servicesArray)) {
            return $this->servicesArray[$key];
        }

        if (! $this->isAdditionalConfig($key)) {
            throw new MissingConfigException("Missing services entry for $this->providerName.$key");
        }

        return $key === 'guzzle' ? [] : null;
    }

    /**
     * Get the configuration from a service as an array.
     *
     * @param  string  $providerName
     * @return array
     * @throws \SocialiteProviders\Manager\Exception\MissingConfigException
     */
    protected function getConfigFromServicesArray($providerName)
    {
        $configArray = config("services.{$providerName}");

        if (empty($configArray)) {
            // If we are running in console we should spoof values to make Socialite happy...
            if (app()->runningInConsole()) {
                $configArray = [
                    'client_id' => "{$this->providerIdentifier}_KEY",
                    'client_secret' => "{$this->providerIdentifier}_SECRET",
                    'redirect' => "{$this->providerIdentifier}_REDIRECT_URI",
                ];
            } else {
                throw new MissingConfigException("There is no services entry for $providerName");
            }
        }

        return $this->servicesArray = $configArray;
    }

    /**
     * Checks if an additional configuration with this key exists.
     *
     * @param  string  $key
     * @return bool
     */
    protected function isAdditionalConfig($key)
    {
        return in_array(strtolower($key), $this->additionalConfigKeys, true);
    }
}
