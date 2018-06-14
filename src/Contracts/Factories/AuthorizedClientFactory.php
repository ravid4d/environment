<?php

namespace AmcLab\Tenancy\Contracts\Factories;

use GuzzleHttp\ClientInterface;

interface AuthorizedClientFactory {

    public function create(array $config) : ClientInterface;

}
