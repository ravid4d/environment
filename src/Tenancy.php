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

    public function getTenant() : Tenant {
        return $this->tenant;
    }

    /**
     * Procedura per il setup del tenant per la sessione corrente
     *
     * @param string $identity
     * @return void
     */
    public function setIdentity(string $identity) {

        if ($this->getIdentity()){
            throw new TenancyException('Tenancy identity is currently SET', 1001);
        }

        return $this->tenant->setIdentity($identity, [
            'database' => [
                'connection' => $connectionName = 'currentTenant',
                'autoconnect' => true,
                'makeDefault' => true,
                'resolver' => $this->app->make('db'),
            ]
        ])
        ->alignMigrations()
        ->alignSeeds();

    }

    public function unsetIdentity() {
        if ($this->getIdentity()){
            $this->tenant->unsetIdentity();
        }
    }

    public function getIdentity() :? string {
        return $this->tenant->getIdentity();
    }

    public function createIdentity(string $newIdentity, $databaseServer = []) {

        // per i test locali ho usato: Tenancy::createIdentity('CODICE_TENANT','mariadb@local')

        if ($this->getIdentity()){
            throw new TenancyException('Tenancy identity is currently SET', 1001);
        }

        $newTenant = $this->app->make(Tenant::class);
        $newTenant->setConnectionResolver($this->app->make('db'))->getResolver()->bootstrap();
        $newTenant->createIdentity($newIdentity, $databaseServer);

        unset($newTenant);

        return $tenant = $this->setIdentity($newIdentity);

        // TODO: factories tabelle utenti, ruoli, ecc...
        // $tenant->createBaseTables()
        // $tenant->createUsers()....
        // bla bla bla

    }

    public function update(array $customPackage) {

        if (!$this->getIdentity()){
            throw new TenancyException('Tenancy identity must be SET', 1000);
        }

        return $this->tenant->update($customPackage);
    }

    public function updateAndReset(array $customPackage) {

        if (!$current = $this->getIdentity()){
            throw new TenancyException('Tenancy identity must be SET', 1000);
        }

        $this->update($customPackage)
        ->unsetIdentity()
        ->setIdentity($current);

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
        return $this->tenant->getResolver()->get($camel)->use();

    }
}
