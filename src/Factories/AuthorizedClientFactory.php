<?php

namespace AmcLab\Tenancy\Factories;

use AmcLab\Tenancy\Contracts\Factories\AuthorizedClientFactory as Contract;
use GuzzleHttp\ClientInterface;

class AuthorizedClientFactory implements Contract {

    public function create(array $config) : ClientInterface {

        $key = new \Acquia\Hmac\Key($config['key'], $config['secret']);
        $realm = $config['realm'];
        $middleware = new \Acquia\Hmac\Guzzle\HmacAuthMiddleware($key, $realm);
        $stack = \GuzzleHttp\HandlerStack::create();
        $stack->push($middleware);

        return new \GuzzleHttp\Client([
            'base_uri' => $config['url'],
            'handler' => $stack,
        ]);

    }

}
