<?php

namespace AmcLab\Tenancy\Contracts;

interface Hook {

    public function populate(array $config = [], array $concreteParams = [], bool $singleton = true);

    public function use();

    public function purge();

    public function generate(array $generateParams = []) : array;

}
