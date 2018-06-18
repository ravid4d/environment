<?php

namespace AmcLab\Tenancy;

use AmcLab\Tenancy\Contracts\Tenancy as Contract;
use AmcLab\Tenancy\Contracts\Tenant;
use AmcLab\Tenancy\Exceptions\TenancyException;
use BadMethodCallException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;

class Tenancy implements Contract {

    protected $tenant;
    protected $app;
    protected $config;

    public function __construct(Repository $configRepository, Tenant $tenant, Application $app) {
        $this->config = $configRepository->get('tenancy.singleton');
        $this->tenant = $tenant;
        $this->app = $app; // TODO: trovare un modo elegante per farla sparire
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
    public function setIdentity(string $identity) : Tenant {

        if ($this->getIdentity()){
            throw new TenancyException('Tenancy identity is currently SET');
        }

        return $this->tenant->setIdentity($identity, [
            'database' => [
                'connection' => $connection = 'currentTenant',
                'autoconnect' => true,
                'makeDefault' => true,
                'resolver' => $this->app->make('db'),
            ]
        ])
        ->alignMigrations($connection)
        ->alignSeeds($connection);

    }

    public function unsetIdentity() : Tenant {
        if ($this->getIdentity()){
            $this->tenant->unsetIdentity();
        }

        return $this->tenant;
    }

    public function getIdentity() :? string {
        return $this->tenant->getIdentity();
    }

    public function createIdentity($newIdentity) {

        if ($this->getIdentity()){
            throw new TenancyException('Tenancy identity is currently SET');
        }

        $newTenant = $this->app->make(Tenant::class)
        ->setConnectionResolver($this->app->make('db'));

        $newTenant->getResolver()->bootstrap();

        $newTenant->createIdentity($newIdentity);

        $tenant = $this->setIdentity($newIdentity);

        // TODO: factories tabelle utenti, ruoli, ecc...
        // $tenant->createBaseTables()
        // $tenant->createUsers()....
        // bla bla bla

        return $tenant;

    }

    public function customize(array $customPackage) {

        if (!$this->getIdentity()){
            throw new TenancyException('Tenancy identity must be SET');
        }

        return $this->tenant->customize($customPackage);

    }

    public function __call($name, $args){

        // TODO: mettere altri/migliori controlli sul nome...

        // if ($name === 'set'){
        //     return $this->setIdentity(...$args);
        // }

        if (substr($name, 0, 3) !== 'use') {
            throw new BadMethodCallException("Invalid method ".__CLASS__."->$name() called");
        }

        $studly = substr($name, 3);

        if ($studly !== studly_case($studly)) {
            throw new BadMethodCallException("Invalid method ".__CLASS__."->$name() called");
        }

        $camel = camel_case($studly);
        return $this->tenant->getResolver()->get($camel)->use();

    }
}
