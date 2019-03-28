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
use Illuminate\Support\Str;

class Environment implements Contract {

    protected $tenant;
    protected $app;
    protected $config;
    protected $databaseConnector;
    protected $scope;
    protected $booted;

    public function __construct(Repository $configRepository, Tenant $tenant, Application $app, Scope $scope) {
        $this->config = $configRepository->get('environment.singleton');
        $this->tenant = $tenant;
        $this->app = $app;
        $this->scope = $scope;
    }

    public function boot(ConnectionResolverInterface $databaseConnector) {
        if ($this->booted) {
            throw new EnvironmentException('Environment already booted', 1500);
        }

        $this->databaseConnector = $databaseConnector;
        $this->tenant->setDatabaseConnector($databaseConnector)
        ->getResolver()
        ->bootstrap();

        $this->booted = true;

        return $this;
    }

    public function getSpecs() {
        return [
            'identity'=>$this->getIdentity(),
            'scope'=>$this->getScope(),
        ];
    }

    public function setWithSpecs($specs = null) {
        if ($specs) {
            foreach($specs as $specKey => $specValue) {
                $this->{'set' . Str::studly($specKey)}($specValue);
            }
        }
        return $this;
    }

    public function getScope() {
        return $this->scope;
    }

    public function setScope($scope) {
        $this->scope = $scope;
        return $this;
    }

    public function getTenant() : Tenant {
        if (!$this->booted) {
            throw new EnvironmentException('Environment not booted', 1500);
        }
        return $this->tenant;
    }

    public function exists($identity) {
        return $this->tenant->exists($identity);
    }

    /**
     * Procedura per il setup del tenant per la sessione corrente
     *
     * @param string $identity Stringa contenente l'identity da popolare
     * @return void
     */
    public function setIdentity(string $identity) {

        // effettua la migration automaticamente solo se l'environment è attivo
        if (!$this->setTenantIdentity($identity)->isActive()) {
            throw new EnvironmentException('Environment is currently not active', 503);
        }

        return $this->afterTenantChange();

    }

    protected function setTenantIdentity(string $identity) {
        if (!$this->booted) {
            throw new EnvironmentException('Environment not booted', 1500);
        }

        if ($currentIdentity = $this->getIdentity()){
            throw new EnvironmentException('Environment identity is currently SET: ' . $currentIdentity, 1001);
        }

        $this->tenant->setIdentity($identity, [
            'database' => [
                'connection' => $connectionName = 'currentTenant',
                'autoconnect' => true,
                'makeDefault' => true,
                'connector' => $this->databaseConnector,
            ]
        ]);

        return $this;
    }

    protected function afterTenantChange() {
        $this->tenant
        ->alignMigrations()
        ->alignSeeds();

        return $this;
    }

    public function unsetIdentity() {
        if (!$this->booted) {
            throw new EnvironmentException('Environment not booted', 1500);
        }

        if ($this->getIdentity()){
            $this->tenant->unsetIdentity();
        }

        return $this;
    }

    public function getIdentity() :? string {
        if (!$this->booted) {
            throw new EnvironmentException('Environment not booted', 1500);
        }

        return $this->tenant ? $this->tenant->getIdentity() : null;
    }

    public function isActive() {
        if (!$this->booted) {
            throw new EnvironmentException('Environment not booted', 1500);
        }

        return $this->tenant->isActive();
    }

    public function hasEverBeenMigrated() {
        if (!$this->booted) {
            throw new EnvironmentException('Environment not booted', 1500);
        }

        return $this->tenant->hasEverBeenMigrated();
    }

    public function suspend() {
        $this->tenant->suspend();
        return $this;
    }

    public function wakeup() {
        $this->tenant->wakeup();
        return $this;
    }

    public function createIdentity(string $newIdentity, string $databaseServer) {

        // per i test locali ho usato: Environment::createIdentity('CODICE_TENANT','mariadb@local')

        if ($currentIdentity = $this->getIdentity()){
            throw new EnvironmentException('Environment identity is currently SET: ' . $currentIdentity, 1001);
        }

        $newTenant = $this->app->make(Tenant::class);
        $newTenant->setDatabaseConnector($this->databaseConnector)
        ->getResolver()
        ->bootstrap();

        $newTenant->createIdentity($newIdentity, $databaseServer);
        unset($newTenant);

        return $this->setTenantIdentity($newIdentity)->afterTenantChange();

        // TODO: factories tabelle utenti, ruoli, ecc...
        // $tenant->createBaseTables()
        // $tenant->createUsers()....
        // bla bla bla

    }

    public function update(array $customPackage) {

        if (!$this->getIdentity()){
            throw new EnvironmentException('Environment identity must be SET', 1000);
        }

        $this->tenant->update($customPackage);

        return $this;
    }

    public function updateAndReset(array $customPackage) {

        if (!$current = $this->getIdentity()){
            throw new EnvironmentException('Environment identity must be SET', 1000);
        }

        $this->update($customPackage)
        ->unsetIdentity()
        ->setIdentity($current);

    }

    public function pathway($direction) {
        return $this->getTenant()->getStore()->getPathway()[$direction] ?? null;
    }

    public function __call($name, $args){

        // TODO: mettere altri/migliori controlli sul nome...

        if (substr($name, 0, 3) === 'use') {
            $studly = substr($name, 3);

            if ($studly !== Str::studly($studly)) {
                throw new BadMethodCallException("Invalid method ".__CLASS__."->$name() called");
            }

            $camel = Str::camel($studly);
            return $this->tenant->getResolver()->get($camel)->use();
        }

        else {
            throw new BadMethodCallException("Invalid method ".__CLASS__."->$name() called");
        }


    }

}
