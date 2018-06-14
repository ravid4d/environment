<?php

namespace AmcLab\Tenancy\Contracts\Services;

use AmcLab\Tenancy\Contracts\Services\ConciergeService;

interface LockerService {

    public function getConfig() : array;

    public function get(array $config) : array;

    public function put(array $config, $payload) : array;

    public function delete(array $config) : bool;

    public function post(array $config, $payload) : array;

}
