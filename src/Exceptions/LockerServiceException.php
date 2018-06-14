<?php

namespace AmcLab\Tenancy\Exceptions;

class LockerServiceException extends TenancyException {

    public function __construct($message = '', $resourceId = [], $code = null, $previous = null) {

        parent::__construct(join(' - ', array_filter([
            $message ?: null,
            $code ? 'Code: ' . $code : null,
            $uri = $resourceId ? "resourceId=" . json_encode($resourceId) : null,
        ])), $code, $previous);

    }
}
