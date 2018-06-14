<?php

namespace AmcLab\Tenancy\Contracts;

use AmcLab\Tenancy\Contracts\Services\ConciergeService;
use AmcLab\Tenancy\Contracts\Services\LockerService;

interface Messenger {

    public function withConciergeService(ConciergeService $conciergeService);

    public function withLockerService(LockerService $lockerService) : self;

    public function withShortcuts(array $shortcuts) : self;

    public function subject(...$breadcrumbs) : self;

    public function exists(...$breadcrumbs) : bool;

    public function read(...$breadcrumbs) : array;

    public function write($payload = null, ...$breadcrumbs) : array;

    public function delete(...$breadcrumbs) : bool;

    public function suspend(...$breadcrumbs) : bool;

    public function wakeup(...$breadcrumbs) : bool;

}
