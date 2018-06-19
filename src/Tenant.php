<?php

namespace AmcLab\Tenancy;

use AmcLab\Baseline\Contracts\PackageStore;
use AmcLab\Tenancy\Contracts\Tenant as Contract;
use AmcLab\Tenancy\Exceptions\TenantException;
use AmcLab\Baseline\Traits\HasEventsDispatcherTrait;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\QueryException;

/**
 * Questa classe implementa le singole operazioni dei tenant.
 */
class Tenant implements Contract {

    use HasEventsDispatcherTrait;

    protected $config;

    protected $store;
    protected $resolver;
    protected $kernel;
    protected $db;

    protected $subject;

    protected $identity;

    public function __construct(Repository $configRepository, PackageStore $store, Resolver $resolver, Kernel $kernel, Dispatcher $events) {
        $this->config = $configRepository->get('tenancy.tenant');
        $this->store = $store;
        $this->resolver = $resolver;
        $this->kernel = $kernel;
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

        $this->subject = $this->store->subject($identity);
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
        $this->identity = null;

        $this->fire('tenant.identity.left', ['identity' => $this->identity]);

        return $this;
    }

    public function customize(array $customPackage = []) {

        $this->fire('tenant.customize.begin', ['identity' => $this->identity]);

        $this->subject->customize($customPackage);

        $this->fire('tenant.customize.done', ['identity' => $this->identity]);

        return $this;

    }

    public function alignMigrations($connection) {

        $current = $this->detectMigrationPoint($connection);
        $status = $this->subject->read()['migration'];

        if ($status !== $current) {

            $this->subject->suspend();

            $this->fire('tenant.migrating', ['identity' => $this->identity]);
            $this->kernel->call('migrate', [
                '--force' => true,
                '--database' => $connection,
            ]);
            $this->fire('tenant.migrated', ['identity' => $this->identity]);

            $this->subject->wakeup();

            $status = $this->subject->setMigrationPoint($this->detectMigrationPoint($connection));

        }

        return $this;

    }

    public function detectMigrationPoint($connection) {

        //! NOTE: TODO: FIXME: è decisamente da rivedere, perché non trovo adeguata documentazione su Illuminate\Database\Migrations\MigrationRepositoryInterface
        // determino l'hash della più recente migration presente su questo database
        try {
            $migrationsList = $this->db->connection($connection)->table('migrations')->orderBy('id', 'desc')->pluck('migration');
        }

        // catturo l'eventuale QueryException
        catch (QueryException $e) {

            if (!$e->getCode() === '42S02') {
                throw $e;
            }

            // se arrivo qui, allora vuol dire che non esiste la tabella cercata ('migrations'),
            // ragion per cui è SICURO che sul database corrente debba essere lanciato il migrate.
            $migrationsList = [];
            $this->kernel->call('migrate:install', [
                '--database' => $connection,
            ]);
        }

        return md5(json_encode($migrationsList));
    }

    public function alignSeeds() {
        // STUB: per il momento non fa nulla
        // TODO... bisogna studiare come farlo funzionare in maniera autonoma
        // $this->fire('tenant.seeding', ['identity' => $this->identity]);
        // $this->fire('tenant.seeded', ['identity' => $this->identity]);
        return $this;
    }

    public function createIdentity(string $identity) {
        if ($this->identity) {
            throw new TenantException('Cannot create a Tenant while another Tenant is identified');
        }

        $this->subject = $this->store->subject($identity);

        $databaseServer = null; // TODO:

        $hooks = $this->resolver->getHooks();

        $this->store->create($identity, $hooks, function($identity, $pathway) use ($databaseServer) {

            /* TODO:
                Questa callback dovrebbe richiamare un sistema esterno capace di
                creare, su uno specifico database server, un user ed un database,
                restituendo poi il package da scrivere.

                Per il momento "simula" l'output.
            */

            $this->fire('tenant.database.creating', ['identity' => $identity, 'databaseServer' => $databaseServer]);

            $credentials = [
                'driver' => $databaseServer['driver'] ?? 'mysql',
                //'host' => $databaseServer['host'] ?? ('mariadb'.random_int(1,5).'.example.com'),
                 'host' => $databaseServer['host'] ?? 'mariadb',
                'port' => $databaseServer['port'] ?? '3306',
                'database' => strtoupper(join('_',$pathway['resourceId'])) . '_DB',
                // 'username' => 'user_' . strtoupper(array_last($pathway['normalized'])) . '_' . strtolower(str_random(4)),
                // 'password' => str_random(16),
                'username' => 'root',
                'password' => 'root',
            ];

            $this->fire('tenant.database.created', ['identity' => $identity, 'databaseServer' => $databaseServer]);

            return $credentials;

        });

        return $this;
    }


}
