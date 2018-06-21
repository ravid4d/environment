<?php

namespace AmcLab\Tenancy;

use AmcLab\Baseline\Contracts\PackageStore;
use AmcLab\Baseline\Contracts\PersistenceManager;
use AmcLab\Baseline\Traits\HasEventsDispatcherTrait;
use AmcLab\Tenancy\Contracts\MigrationManager;
use AmcLab\Tenancy\Contracts\Tenant as Contract;
use AmcLab\Tenancy\Exceptions\TenantException;
use Exception;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\QueryException;

/**
 * Questa classe implementa le singole operazioni dei tenant.
 */
class Tenant implements Contract {

    use HasEventsDispatcherTrait;

    protected $config;

    protected $migrationManager;
    protected $store;
    protected $resolver;
    protected $persister;
    protected $db;

    protected $identity;

    public function __construct(Repository $configRepository, MigrationManager $migrationManager, PackageStore $store, Resolver $resolver, PersistenceManager $persister, Dispatcher $events) {
        $this->config = $configRepository->get('tenancy.tenant');
        $this->migrationManager = $migrationManager;
        $this->store = $store;
        $this->resolver = $resolver;
        $this->persister = $persister;
        $this->setEventsDispatcher($events);
    }

    public function setConnectionResolver(ConnectionResolverInterface $db) {
        $this->db = $db;
        return $this;
    }

    public function getConnectionResolver() {
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
            throw new TenantException('Database resolver must be set');
        }

        $this->fire('tenant.setIdentity', ['identity' => $identity]);

        $this->store->setPathway($identity);

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
                'resolver' => $this->db,
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

    public function update(array $customPackage = []) {

        $this->fire('tenant.update', ['identity' => $this->identity]);

        $this->store->update($customPackage);

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
                throw new TenantException('Someone else is migrating here or previous migration failed...');
            }

            $this->store->request('beginMigrate');

            try {
                $newStatus = $this->migrationManager->attempt();
                $postMigrationPayload = ['migration' => $newStatus];
            }

            catch (Exception $e) {
                $this->fire('tenant.alignMigration.failed', ['identity' => $this->identity, 'exception' => json_encode($e)]);
                $postMigrationPayload = ['failed'=> true];
                $quitReason = $e;
            }

            $this->store->request('endMigrate', $postMigrationPayload);

            if ($quitReason ?? false) {
                throw new TenantException('Migration failed. Tenant is now marked as *NOT* ACTIVE!', 500, $quitReason);
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
            throw new TenantException('Cannot create a Tenant while another Tenant is identified');
        }

        $this->fire('tenant.createIdentity', ['identity' => $identity, 'databaseServer' => $databaseServer]);

        $this->store->setPathway($identity);
        $hooks = $this->resolver->getHooks();
        $persister = $this->persister->setServer($databaseServer);
        $this->store->create($hooks, $persister);

        return $this;
    }


}
