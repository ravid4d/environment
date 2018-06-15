<?php

namespace AmcLab\Tenancy\Exceptions;

class ResolverException extends TenancyException {

    public function __construct($message = null, $code = null, $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
