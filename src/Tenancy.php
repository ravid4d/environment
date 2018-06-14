<?php

namespace AmcLab\Tenancy;

use AmcLab\Tenancy\Contracts\Tenant;
use AmcLab\Tenancy\Exceptions\TenancyException;
use AmcLab\Tenancy\Traits\HasEventsDispatcherTrait;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;

class Tenancy {

    use HasEventsDispatcherTrait;

    protected $tenant;

    public function __construct(Tenant $tenant, Application $app) {
        $this->tenant = $tenant;
        $this->app = $app;
    }

    public function getTenant() {
        return $this->tenant;
    }

    /**
     * Procedura per il setup del tenant per la sessione corrente
     *
     * @param string $identity
     * @return void
     */
    public function assignTo(string $identity) : void {

        if ($this->getIdentity()){
            throw new TenancyException('Tenancy is already assigned');
        }

        $this->tenant
        ->setIdentity($identity, [
            'database' => [
                'connection' => 'currentTenant',
                'autoconnect' => true,
                'makeDefault' => true,
            ]
        ])
        ->alignMigrations()
        ->alignSeeds();

    }

    public function leave() : void {
        if ($this->getIdentity()){
            $this->tenant->unsetIdentity();
        }
    }

    public function getIdentity() :? string {
        return $this->getTenant()->getIdentity();
    }

    public function create($newIdentity) {

        if ($this->getIdentity()){
            throw new TenancyException('Tenancy identity must be unset to proceed');
        }

        $newTenant = $this->app->make(Tenant::class)
        ->createIdentity($newIdentity);

        $this->assign($newIdentity);

        // TODO: factories tabelle utenti, ruoli, ecc...

    }

    public function __call($name, $args){

        // TODO: mettere altri/migliori controlli sul nome...

        if (substr($name, 0, 3) !== 'use') {
            throw new BadMethodCallException("Invalid method ".__CLASS__."->$name() called");
        }

        $studly = substr($name, 3);

        if ($studly !== studly_case($studly)) {
            throw new BadMethodCallException("Invalid method ".__CLASS__."->$name() called");
        }

        $camel = camel_case($studly);
        return $this->getTenant()->getResolver()->getHooks()['tenancy.hook.' . $camel]['instance']->use();

    }
}
