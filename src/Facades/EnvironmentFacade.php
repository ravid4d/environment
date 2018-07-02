<?php

namespace AmcLab\Environment\Facades;

use Illuminate\Support\Facades\Facade;

class EnvironmentFacade extends Facade {

    protected static function getFacadeAccessor() {
        return 'environment';
    }

}
