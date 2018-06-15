<?php

namespace AmcLab\Tenancy\Factories;

use AmcLab\Tenancy\Contracts\Factories\AuthorizedClientFactory as Contract;
use GuzzleHttp\ClientInterface;

class AuthorizedClientFactory implements Contract {

    public function create(array $config) : ClientInterface {

        $key = new \Acquia\Hmac\Key($config['keys']['public'] ?? '', $config['keys']['secret'] ?? '');
        $realm = $config['realm'] ?? 'Restricted';
        $middleware = new \Acquia\Hmac\Guzzle\HmacAuthMiddleware($key, $realm, $config['headers'] ?? []);
        $stack = \GuzzleHttp\HandlerStack::create();
        $stack->push($middleware);

        return new \GuzzleHttp\Client([
            'base_uri' => $config['url'],
            'handler' => $stack,
        ]);

    }

}
