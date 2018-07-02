<?php

namespace AmcLab\Environment;

use AmcLab\Environment\Contracts\Environment as Contract;
use AmcLab\Environment\Contracts\Scope;
use AmcLab\Environment\Contracts\Tenant;
use AmcLab\Environment\Exceptions\EnvironmentException;
use BadMethodCallException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;

class Environment implements Contract, \Serializable {

    protected $tenant;
    protected $app;
    protected $config;
    protected $databaseConnector;
    protected $scope;

    public function __construct(Repository $configRepository, Tenant $tenant, Application $app, Scope $scope) {
        $this->config = $configRepository->get('environment.singleton');
        $this->tenant = $tenant;
        $this->app = $app;
        $this->scope = $scope;
    }

    public function serialize() {
        return serialize([
            'identity'=>$this->getIdentity(),
            'scope'=>$this->getScope(),
        ]);
    }

    public function unserialize($serialized) {
        $params = unserialize($serialized);
        $this->app = app();
        $this->config = app('config')->get('environment.singleton');
        $this->tenant = app(Tenant::class);
        $this->boot()->setIdentity($params['identity']);
        $this->scope = $params['scope'];
    }

    public function boot() {
        $this->databaseConnector = $this->app->make('db');
        $this->tenant->setDatabaseConnector($this->databaseConnector)
        ->getResolver()
        ->bootstrap();
        return $this;
    }

    public function getScope() {
        return $this->scope;
    }

    public function setScope() {
        $this->scope = $scope;
        return $this;
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
            throw new EnvironmentException('Environment identity is currently SET', 1001);
        }

        return $this->tenant->setIdentity($identity, [
            'database' => [
                'connection' => $connectionName = 'currentTenant',
                'autoconnect' => true,
                'makeDefault' => true,
                'connector' => $this->databaseConnector,
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

    public function createIdentity(string $newIdentity, string $databaseServer) {

        // per i test locali ho usato: Environment::createIdentity('CODICE_TENANT','mariadb@local')

        if ($this->getIdentity()){
            throw new EnvironmentException('Environment identity is currently SET', 1001);
        }

        $newTenant = $this->app->make(Tenant::class);
        $newTenant->setDatabaseConnector($this->databaseConnector)
        ->getResolver()
        ->bootstrap();

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
            throw new EnvironmentException('Environment identity must be SET', 1000);
        }

        return $this->tenant->update($customPackage);
    }

    public function updateAndReset(array $customPackage) {

        if (!$current = $this->getIdentity()){
            throw new EnvironmentException('Environment identity must be SET', 1000);
        }

        $this->update($customPackage)
        ->unsetIdentity()
        ->setIdentity($current);

    }

    public function pathway() {
        return $this->getTenant()->getStore()->getPathway();
    }

    public function __call($name, $args){

        // TODO: mettere altri/migliori controlli sul nome...

        if (substr($name, 0, 3) === 'use') {
            $studly = substr($name, 3);

            if ($studly !== studly_case($studly)) {
                throw new BadMethodCallException("Invalid method ".__CLASS__."->$name() called");
            }

            $camel = camel_case($studly);
            return $this->tenant->getResolver()->get($camel)->use();
        }

        else {
            throw new BadMethodCallException("Invalid method ".__CLASS__."->$name() called");
        }


    }

}
