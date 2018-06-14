<?php

namespace AmcLab\Tenancy;

use AmcLab\Tenancy\Contracts\Hook;
use AmcLab\Tenancy\Contracts\Resolver as Contract;
use AmcLab\Tenancy\Traits\HasEventsDispatcherTrait;

class Resolver implements Contract {

    use HasEventsDispatcherTrait;

    protected $hooks;
    protected $populated = false;

    public function __construct(array $config, Hook ...$instances) {

        foreach ($instances as $index => $instance) {
            $hookAlias = $config[$index]['alias'];
            $hookConfig = $config[$index]['config'];

            $this->hooks[$hookAlias] = [
                'instance' => $instance,
                'config' => $hookConfig,
            ];
        }

    }

    public function getHooks() : array {
        return $this->hooks;
    }

    public function populate(array $package = [], $params = []) : void {

        foreach ($this->hooks as &$hook) {

            $hook['instance']->purge();

            if ($packageEntry = $package[$hook['config']['packageEntry']] ?? null){
                $hook['instance']->populate($packageEntry, $params);
            }

        }

    }

    public function purge() : void {

        foreach ($this->hooks as &$hook) {
            $hook['instance']->purge();
        }

        $this->populated = false;

    }

    public function isPopulated() : bool {
        return $this->populated;
    }

}
