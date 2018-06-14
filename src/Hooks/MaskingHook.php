<?php

namespace AmcLab\Tenancy\Hooks;

use AmcLab\Disorder\Disorder;
use AmcLab\Tenancy\Abstracts\AbstractHook;
use AmcLab\Tenancy\Contracts\Hook as Contract;

class MaskingHook extends AbstractHook implements Contract {

    protected function concrete(array $config = [], array $concreteParams = []) {

        return unserialize($config['serialized']);

    }

    public function generate(array $generateParams = []) : array {

        $instance = (new Disorder)->init(null, random_bytes(16));

        return ['serialized' => serialize($instance)];
    }
}
