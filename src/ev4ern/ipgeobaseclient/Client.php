<?php

namespace ev4ern\ipgeobaseclient;

use ev4ern\ipgeobaseclient\exceptions\InvalidIPException;
use ev4ern\ipgeobaseclient\exceptions\IPNotFoundException;
use ev4ern\ipgeobaseclient\exceptions\ResponseException;
use Memcached;
use SimpleXMLElement;

/**
 * Class Client
 *
 * @package ev4ern\ipgeobaseclient
 */
class Client
{
    const TIMEOUT         = 3;
    const CONNECT_TIMEOUT = 3;

    /**
     * @var bool
     */
    private $useMemcached = false;

    /**
     * @var string
     */
    private $memcacheHost = '127.0.0.1';

    /**
     * @var int
     */
    private $memcachePort = 11211;

    /**
     * @var int
     */
    private $memcacheExpire = 0;

    /**
     * @var string
     */
    private $country;

    /**
     * @var string
     */
    private $city;

    /**
     * @var string
     */
    private $region;

    /**
     * @var string
     */
    private $district;

    /**
     * @var Memcached
     */
    private $memcachedClient;

    /**
     * @var string
     */
    private $memcachedPrefix = "ipgeoclient";

    /**
     * @var string
     */
    private $ip;

    /**
     * @param boolean $useMemcached
     *
     * @return $this
     */
    public function setUseMemcached($useMemcached)
    {
        $this->useMemcached = $useMemcached;

        return $this;
    }

    /**
     * @param string $memcacheHost
     *
     * @return $this
     */
    public function setMemcacheHost($memcacheHost)
    {
        $this->memcacheHost = $memcacheHost;

        return $this;
    }

    /**
     * @param int $memcachePort
     *
     * @return $this
     */
    public function setMemcachePort($memcachePort)
    {
        $this->memcachePort = $memcachePort;

        return $this;
    }

    /**
     * @param int $memcacheExpire
     *
     * @return $this
     */
    public function setMemcacheExpire($memcacheExpire)
    {
        $this->memcacheExpire = $memcacheExpire;

        return $this;
    }

    /**
     * @param string $memcachedPrefix
     *
     * @return Client
     */
    public function setMemcachedPrefix($memcachedPrefix)
    {
        $this->memcachedPrefix = $memcachedPrefix;

        return $this;
    }

    /**
     * @param string $ip
     *
     * @return Client
     */
    public function setIp($ip)
    {
        $this->ip = $ip;

        return $this;
    }

    /**
     * @return string
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @return string
     */
    public function getRegion()
    {
        return $this->region;
    }

    /**
     * @return string
     */
    public function getDistrict()
    {
        return $this->district;
    }

    /**
     * @return $this
     */
    public function request()
    {
        $this->clearFields();
        $this->validateIP();

        if ($this->useMemcached) {
            $this->initMemcachedClient();
        }

        if ($this->useMemcached && $this->isInitFieldsFromCache()) {
            return $this;
        }

        $this->initFieldsFromXML($this->getXML());

        if ($this->useMemcached) {
            $this->saveToCache();
        }

        return $this;
    }

    /**
     * @return string
     * @throws ResponseException
     */
    private function getXML()
    {
        $url = "http://ipgeobase.ru:7020/geo?ip={$this->ip}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
        $response = curl_exec($ch);

        if ($response === false) {
            throw new ResponseException("Unable to get information for {$this->ip}");
        }

        $response = iconv('windows-1251', 'utf-8', $response);
        $response = str_replace('windows-1251', 'utf-8', $response);

        return $response;
    }

    /**
     * @return bool
     */
    private function isInitFieldsFromCache()
    {
        $country  = $this->memcachedClient->get("{$this->memcachedPrefix}_{$this->ip}_country");
        $city     = $this->memcachedClient->get("{$this->memcachedPrefix}_{$this->ip}_city");
        $region   = $this->memcachedClient->get("{$this->memcachedPrefix}_{$this->ip}_region");
        $district = $this->memcachedClient->get("{$this->memcachedPrefix}_{$this->ip}_district");

        if ($country === false || $city === false || $region === false || $district === false) {
            return false;
        }

        $this->country  = $country;
        $this->city     = $city;
        $this->region   = $region;
        $this->district = $district;

        return true;
    }

    /**
     * @param string $xml
     *
     * @throws IPNotFoundException
     */
    private function initFieldsFromXML($xml)
    {
        $geo = new SimpleXMLElement($xml);

        /* @var SimpleXMLElement $ip */
        $ip = $geo->ip;

        if (!isset($ip->country) || !isset($ip->city) || !isset($ip->region) || !isset($ip->district)) {
            throw new IPNotFoundException("IP address {$this->ip} not found");
        }

        /**
         * object(SimpleXMLElement)#4 (8) {
         * ["@attributes"]=>
         * array(1) {
         * ["value"]=>
         * string(13) "5.102.159.150"
         * }
         * ["inetnum"]=>
         * string(27) "5.102.152.0 - 5.102.159.255"
         * ["country"]=>
         * string(2) "RU"
         * ["city"]=>
         * string(24) "Екатеринбург"
         * ["region"]=>
         * string(39) "Свердловская область"
         * ["district"]=>
         * string(52) "Уральский федеральный округ"
         * ["lat"]=>
         * string(9) "56.837814"
         * ["lng"]=>
         * string(9) "60.596844"
         * }
         */

        $this->country  = (string) $ip->country;
        $this->city     = (string) $ip->city;
        $this->region   = (string) $ip->region;
        $this->district = (string) $ip->district;
    }

    private function saveToCache()
    {
        $this->memcachedClient->set("{$this->memcachedPrefix}_{$this->ip}_country", $this->country);
        $this->memcachedClient->set("{$this->memcachedPrefix}_{$this->ip}_city", $this->city);
        $this->memcachedClient->set("{$this->memcachedPrefix}_{$this->ip}_region", $this->region);
        $this->memcachedClient->set("{$this->memcachedPrefix}_{$this->ip}_district", $this->district);
    }

    /**
     * @throws InvalidIPException
     */
    private function validateIP()
    {
        if (!filter_var($this->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new InvalidIPException("IP address {$$this->ip} is invalid");
        }
    }

    private function initMemcachedClient()
    {
        $this->memcachedClient = new Memcached();
        $this->memcachedClient->addServer($this->memcacheHost, $this->memcachePort);
    }

    private function clearFields()
    {
        $this->country  = NULL;
        $this->city     = NULL;
        $this->region   = NULL;
        $this->district = NULL;
    }
}
