<?php

namespace AmcLab\Tenancy\Exceptions;

class HasConfigTraitException extends TenancyException {

    public function __construct($message = null, $code = null, $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
