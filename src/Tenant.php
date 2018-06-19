<?php

namespace AmcLab\Tenancy;

use AmcLab\Baseline\Contracts\PackageStore;
use AmcLab\Baseline\Contracts\PersistenceManager;
use AmcLab\Baseline\Traits\HasEventsDispatcherTrait;
use AmcLab\Tenancy\Contracts\MigrationManager;
use AmcLab\Tenancy\Contracts\Tenant as Contract;
use AmcLab\Tenancy\Exceptions\TenantException;
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

    protected $subject;

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

    public function getSubject() {
        return $this->subject;
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

        $this->fire('tenant.identity.setting', ['identity' => $identity]);

        $this->subject = $this->store->setSubject($identity);
        $response = $this->subject->read();

        $this->resolver->populate($response['disclosed'], $concreteParams + [
            'database' => [
                'connection' => 'tenant_' . str_random(8),
                'resolver' => $this->db,
            ]
        ]);

        $this->identity = $identity;

        $this->fire('tenant.identity.set', ['identity' => $identity]);

        return $this;
    }

    public function unsetIdentity() {
        $this->fire('tenant.identity.leaving', ['identity' => $this->identity]);

        $this->resolver->purge();
        $this->store->unsetSubject();
        $this->subject = null;
        $this->identity = null;

        $this->fire('tenant.identity.left', ['identity' => $this->identity]);

        return $this;
    }

    public function update(array $customPackage = []) {

        $this->fire('tenant.update.begin', ['identity' => $this->identity]);

        $this->subject->update($customPackage);

        $this->fire('tenant.update.done', ['identity' => $this->identity]);

        return $this;

    }

    public function alignMigrations() {

        $localConnection = $this->resolver->use('database');

        if (!$localMigrationStatus = $this->subject->read()['migration']) {
            $this->migrationManager->install($localConnection);
        }

        $appMigrationStatus = $this->migrationManager->getAppStatus();

        if ($localMigrationStatus !== $appMigrationStatus) {

            $this->subject->request('suspend');

            $this->fire('tenant.migrating', ['identity' => $this->identity]);

            $newStatus = $this->migrationManager->attempt($localConnection);

            $this->fire('tenant.migrated', ['identity' => $this->identity]);

            //$newStatus = $this->migrationManager->getLocalStatus($localConnection);

            $this->subject->request('setMigrationPoint', [
                'migration' => $newStatus,
            ]);

            $this->subject->request('wakeup');

        }

        return $this;
    }

    public function alignSeeds() {
        // STUB: per il momento non fa nulla
        // TODO... bisogna studiare come farlo funzionare in maniera autonoma
        // $this->fire('tenant.seeding', ['identity' => $this->identity]);
        // $this->fire('tenant.seeded', ['identity' => $this->identity]);
        return $this;
    }

    public function createIdentity(string $identity, $databaseServer = []) {
        if ($this->identity) {
            throw new TenantException('Cannot create a Tenant while another Tenant is identified');
        }

        $this->subject = $this->store->setSubject($identity);

        $hooks = $this->resolver->getHooks();

        $persister = $this->persister->setServer($databaseServer);

        $this->fire('tenant.database.creating', ['identity' => $identity, 'databaseServer' => $databaseServer]);

        $this->store->create($identity, $hooks, $persister);

        $this->fire('tenant.database.created', ['identity' => $identity, 'databaseServer' => $databaseServer]);

        return $this;
    }


}
