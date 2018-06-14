<?php

namespace AmcLab\Tenancy\Exceptions;

use RuntimeException;

class TenancyException extends RuntimeException {

    public function __construct($message = null, $code = null, $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
