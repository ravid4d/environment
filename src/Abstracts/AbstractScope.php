<?php

namespace AmcLab\Environment\Abstracts;

use AmcLab\Environment\Contracts\Scope as Contract;
use AmcLab\Environment\Exceptions\ScopeException;

abstract class AbstractScope implements Contract, \Serializable {

    protected $data = [];

    public function getData() {
        return $this->data;
    }

    public function setData($data) {
        $this->data = $data;
        return $this;
    }

    public function serialize() {
        return serialize([
            'data' => $this->data
        ]);
    }

    public function unserialize($serialized) {
        $unserialized = unserialize($serialized);
        $this->data = $unserialized['data'];
    }

}
