<?php

namespace AmcLab\Environment;

use AmcLab\Baseline\Contracts\PackageStore;
use AmcLab\Baseline\Contracts\PersistenceManager;
use AmcLab\Baseline\Traits\HasEventsDispatcherTrait;
use AmcLab\Environment\Contracts\MigrationManager;
use AmcLab\Environment\Contracts\Tenant as Contract;
use AmcLab\Environment\Exceptions\TenantException;
use Exception;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\QueryException;
use Psr\Log\LoggerInterface;

/**
 * Questa classe implementa le singole operazioni dei tenant.
 */
class Tenant implements Contract {

    use HasEventsDispatcherTrait;

    protected $config;

    protected $configRepository;
    protected $migrationManager;
    protected $store;
    protected $resolver;
    protected $persister;
    protected $logger;
    protected $db;

    protected $identity;

    public function __construct(Repository $configRepository, MigrationManager $migrationManager, PackageStore $store, Resolver $resolver, PersistenceManager $persister, Dispatcher $events, LoggerInterface $logger) {
        $this->configRepository = $configRepository;
        $this->config = $configRepository->get('environment.tenant');
        $this->migrationManager = $migrationManager;
        $this->store = $store;
        $this->resolver = $resolver;
        $this->persister = $persister;
        $this->setEventsDispatcher($events);
        $this->logger = $logger;
    }

    public function setDatabaseConnector(ConnectionResolverInterface $db) {
        $this->db = $db;
        return $this;
    }

    public function getDatabaseConnector() {
        return $this->db;
    }

    public function getStore() {
        return $this->store;
    }

    public function getResolver() {
        return $this->resolver;
    }

    public function getIdentity() {
        return $this->identity;
    }

    public function setIdentity($identity, $concreteParams = []) {
        if (!$this->db) {
            throw new TenantException('Database resolver must be set', 1000);
        }

        $this->fire('tenant.setIdentity', ['identity' => $identity]);

        $this->store->setPathway('tenant', $identity);

        try {
            $response = $this->store->read();
        }
        catch (Exception $e) {
            $this->unsetIdentity();
            throw $e;
        }

        $this->resolver->populate($response['disclosed'], $concreteParams + [
            'database' => [
                'connection' => 'tenant_' . str_random(8),
                'connector' => $this->db,
            ]
        ]);

        $this->identity = $identity;

        return $this;
    }

    public function unsetIdentity() {
        $this->fire('tenant.unsetIdentity', ['identity' => $this->identity]);

        $this->resolver->purge();
        $this->store->unsetPathway();
        $this->identity = null;

        return $this;
    }

    public function isActive() {
        return $this->store->isActive();
    }

    public function exists($identity) {
        if (!$this->db) {
            throw new TenantException('Database resolver must be set', 1000);
        }

        $this->fire('tenant.exists', ['identity' => $identity]);

        $temporary = (new $this($this->configRepository, $this->migrationManager, clone $this->store, $this->resolver, $this->persister, $this->events, $this->logger))
        ->setDatabaseConnector($this->db)
        ->getStore()
        ->setPathway('tenant', $identity);

        try {
            // lo cerco senza bypassare la cache... eventualmente l'errore 404 viene intercettato dopo
            return $temporary->exists(false);
        }

        catch (Exception $e) {
            if ($e->getCode() === 404) {
                return false;
            }
            throw $e;
        }

        finally {
            unset($temporary);
        }

    }
    /////////////////////////////////////////////
    public function update(array $customPackage = []) {

        $this->fire('tenant.update', ['identity' => $this->identity]);

        $this->store->update($customPackage);

        return $this;

    }

    public function suspend() {
        $this->store->request('suspend');
        return $this;
    }

    public function wakeup() {
        $this->store->request('wakeup');
        return $this;
    }

    public function alignMigrations() {

        $localConnection = $this->resolver->use('database');

        $localMigrationStatus = $this->store->read()['migration'];

        $this->migrationManager->setConnection($localConnection);

        if ($localMigrationStatus === null) {
            $this->migrationManager->install();
        }

        $appMigrationStatus = $this->migrationManager->getAppStatus();

        if ($localMigrationStatus !== $appMigrationStatus) {

            $this->fire('tenant.alignMigration.needed', ['identity' => $this->identity]);

            if ($this->store->read()['migrating']) {
                $this->logger->error($e);
                throw new TenantException('Someone else is migrating here or previous migration is freezed...', 1409);
            }

            $wasActive = $this->isActive();

            $this->suspend();
            $this->store->request('beginMigrate');

            try {
                $newStatus = $this->migrationManager->attempt();
                $postMigrationPayload = ['migration' => $newStatus];
                if ($wasActive) {
                    $this->wakeup();
                }
            }

            catch (Exception $e) {
                $this->fire('tenant.alignMigration.failed', ['identity' => $this->identity, 'exception' => $e]);
                $postMigrationPayload = ['failed'=> true];
                $quitReason = $e;
            }

            $this->store->request('endMigrate', $postMigrationPayload);

            if ($quitReason ?? false) {
                $this->logger->critical($quitReason);
                throw new TenantException('Migration failed. Tenant is now marked as *NOT* ACTIVE!', 1503, $quitReason);
            }

        }

        return $this; // isset($quits) ? $this->unsetIdentity() : $this;
    }

    public function alignSeeds() {
        // STUB: per il momento non fa nulla
        // TODO... bisogna studiare come farlo funzionare in maniera autonoma
        return $this;
    }

    public function createIdentity(string $identity, $databaseServer = []) {
        if ($this->identity) {
            throw new TenantException('Cannot create a Tenant while another Tenant is identified', 1403);
        }

        $this->fire('tenant.createIdentity', ['identity' => $identity, 'databaseServer' => $databaseServer]);

        $this->store->setPathway('tenant', $identity);
        $hooks = $this->resolver->getHooks();
        $persister = $this->persister
        ->setDatabaseConnector($this->db)
        ->setServerIdentity($databaseServer);

        $this->store->create($hooks, $persister);

        return $this;
    }


}
