<?php

namespace AmcLab\Tenancy\Contracts\Services;

interface ConciergeService {

    public function generate(string $key, ...$args) :? string;

}
