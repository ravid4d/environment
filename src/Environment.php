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
                $this->{'set'.studly_case($specKey)}($specValue);
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

    /**
     * Procedura per il setup del tenant per la sessione corrente
     *
     * @param string $identity
     * @return void
     */
    public function setIdentity(string $identity) {

        if (!$this->booted) {
            throw new EnvironmentException('Environment not booted', 1500);
        }

        if ($currentIdentity = $this->getIdentity()){
            throw new EnvironmentException('Environment identity is currently SET: ' . $currentIdentity, 1001);
        }

        try {

            $this->tenant->setIdentity($identity, [
                'database' => [
                    'connection' => $connectionName = 'currentTenant',
                    'autoconnect' => true,
                    'makeDefault' => true,
                    'connector' => $this->databaseConnector,
                ]
            ]);

            // effettua la migration automaticamente solo se l'environment è attivo
            if ($this->isActive()) {
                $this->tenant
                ->alignMigrations()
                ->alignSeeds();
            }
            else {
                throw new EnvironmentException('Environment is currently not active', 503);
            }

        }

        // intercetto le eccezioni per gestire, se necessario, il log
        catch (EnvironmentException $e) {

            // es.: errori di concorrenza (molto rari, se non da cli)
            if ($e->getCode() >= 1400) {
                Log::error($e);
            }

            // es.: migration non riuscita ed environment bloccato
            else if ($e->getCode() >= 1500) {
                Log::critical($e);
            }

            throw $e;

        }

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

    public function suspend() {
        $this->tenant->suspend();
    }

    public function wakeup() {
        $this->tenant->wakeup();
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
