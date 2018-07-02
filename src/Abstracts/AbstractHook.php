<?php

namespace AmcLab\Environment\Abstracts;

use AmcLab\Environment\Contracts\Hook as Contract;
use AmcLab\Environment\Exceptions\HookException;

abstract class AbstractHook implements Contract {

    protected $instance = null;
    protected $entry;

    public function __construct() {
        $this->entry = camel_case(substr(class_basename(static::class), 0, -4));
    }

    final public function populate(array $config = [], array $concreteParams = [], bool $singleton = true) {

        if ( ($singleton) && (!$this->instance) || !$singleton) {
            $this->instance = $this->concrete($config, $concreteParams);
        }

        return $this->instance;

    }

    final public function getEntry() {
        return $this->entry;
    }

    final public function use() {
        if ($this->instance) {
            return $this->instance;
        }

        throw new HookException(static::class . ' is not populated', 1000);
    }

    final public function purge() : void {

        if ($this->instance) {
            $this->destroy();
            $this->instance = null;
        }

        return;
    }

    protected function destroy() {
        // STUB: va (se necessario) implementata dalle classi figlie se devono eseguire dei task
    }

    abstract protected function concrete(array $config = [], array $concreteParams);

    abstract public function generate(array $generateParams = []) : array;

}
