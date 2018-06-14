<?php

namespace AmcLab\Tenancy\Traits;

use AmcLab\Tenancy\Exceptions\HasConfigTraitException;
use Illuminate\Contracts\Events\Dispatcher;

trait HasConfigTrait {

    protected $config;

    final public function setConfig(array $config) : self {
        if ($this->config) {
            throw new HasConfigTraitException('Instance of ' . __CLASS__ . ' already configured');
        }

        $this->config = $config;
        return $this;
    }

    final public function getConfig() {
        return $this->config;
    }

}
