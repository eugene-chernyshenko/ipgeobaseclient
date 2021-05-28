# ipgeobaseclient

Client for [ipgeobase.ru](http://ipgeobase.ru)

Usage:
------

Without memcached

```
use eugenechernyshenko\ipgeobaseclient\Client;

$client = new Client();

$client->setIp("5.102.159.150")->request();

var_dump($region->getRegion());
```

With memcached

```
use eugenechernyshenko\ipgeobaseclient\Client;

$client = (new Client())
    ->setUseMemcached(true)
    ->setMemcacheHost("127.0.0.1")
    ->setMemcachePort(11211)
    ->setMemcacheExpire(30 * 24 * 3600); // 30 days of expire


$client->setIp("5.102.159.150")->request();

var_dump($client->getRegion());
var_dump($client->getCity());
var_dump($client->getCountry());
var_dump($client->getDistrict());
```
