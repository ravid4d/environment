<?php

namespace AmcLab\Tenancy\Facades;

use Illuminate\Support\Facades\Facade;

class TenancyFacade extends Facade {

    protected static function getFacadeAccessor() {
        return 'tenancy';
    }

}
