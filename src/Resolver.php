<?php

namespace AmcLab\Tenancy;

use AmcLab\Tenancy\Contracts\Hook;
use AmcLab\Tenancy\Contracts\Resolver as Contract;
use AmcLab\Tenancy\Traits\HasConfigTrait;
use AmcLab\Tenancy\Traits\HasEventsDispatcherTrait;

class Resolver implements Contract {

    use HasEventsDispatcherTrait;
    use HasConfigTrait;

    protected $hooks;
    protected $populated = false;

    public function __construct() {
    }

    public function setHooks(array $hooks) {
        $this->hooks = $hooks;
        return $this;
    }

    public function getHooks() : array {
        return $this->hooks;
    }

    public function get($entryName) {
        $entry = array_filter($this->hooks, function($v) use ($entryName){
            return $v->getEntry() === $entryName;
        }) ?? [];
        return array_pop($entry);
    }

    public function populate(array $package = [], array $params = []) : void {

        foreach ($this->hooks as &$hook) {

            $hook->purge();
            $entry = camel_case(substr(class_basename(get_class($hook)), 0, -4));

            if ($packageEntry = $package[$entry] ?? null){
                $hook->populate($packageEntry, $params);
            }

        }

    }

    public function purge() : void {

        foreach ($this->hooks as &$hook) {
            $hook->purge();
        }

        $this->populated = false;

    }

    public function isPopulated() : bool {
        return $this->populated;
    }

}
