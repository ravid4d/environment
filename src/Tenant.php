<?php

namespace AmcLab\Tenancy;

use AmcLab\Tenancy\Contracts\Messenger;
use AmcLab\Tenancy\Contracts\Pathfinder;
use AmcLab\Tenancy\Contracts\Resolver;
use AmcLab\Tenancy\Contracts\Tenant as Contract;
use AmcLab\Tenancy\Exceptions\TenantException;
use AmcLab\Tenancy\Traits\HasConfigTrait;
use AmcLab\Tenancy\Traits\HasEventsDispatcherTrait;
use BadMethodCallException;
use Carbon\Carbon;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\QueryException;
use Illuminate\Encryption\Encrypter;
use InvalidArgumentException;

/**
 * Questa classe implementa le singole operazioni dei tenant.
 */
class Tenant implements Contract {

    use HasEventsDispatcherTrait;
    use HasConfigTrait;

    protected $messenger;
    protected $resolver;
    protected $pathfinder;
    protected $kernel;
    protected $db;

    protected $subject;

    protected $identity;

    public function __construct(Pathfinder $pathfinder, Messenger $messenger, Resolver $resolver, Kernel $kernel) {
        $this->pathfinder = $pathfinder;
        $this->resolver = $resolver;
        $this->messenger = $messenger->setPathfinder($pathfinder);
        $this->kernel = $kernel;
    }

    public function setConnectionResolver(ConnectionResolverInterface $db) {
        $this->db = $db;
        return $this;
    }

    public function getMessenger() {
        return $this->messenger;
    }

    public function getResolver() {
        return $this->resolver;
    }

    public function getPathfinder() {
        return $this->pathfinder;
    }

    public function getIdentity() {
        return $this->identity;
    }

    public function setIdentity($identity, $concreteParams = []) {
        $this->fire('tenant.identity.setting', ['identity' => $this->identity]);

        $this->subject = $this->messenger->subject($identity);
        $response = $this->subject->read();

        $this->resolver->populate($response['disclosed'], $concreteParams + [
            'database' => [
                'connection' => 'tenant_' . str_random(8),
            ]
        ]);

        $this->identity = $identity;

        $this->fire('tenant.identity.set', ['identity' => $this->identity]);

        return $this;
    }

    public function unsetIdentity() {
        $this->fire('tenant.identity.leaving', ['identity' => $this->identity]);

        $this->resolver->purge();
        $this->identity = null;

        $this->fire('tenant.identity.left', ['identity' => $this->identity]);

        return $this;
    }

    public function persist($package, $identity = null) {
        throw new \Exception('TODO!');
        $this->messenger->write($package, $identity);
    }

    public function alignMigrations() {

        $tenant = $this->detectMigrationPoint();
        $remote = $this->subject->read()['migration'];

        if ($remote !== $tenant) {

            $this->fire('tenant.migrating', ['identity' => $this->identity]);

            // TODO: riattivare e testare...
            // $this->subject->suspend();
            // $this->kernel->call('migrate', ['--force' => true]);
            // $this->subject->wakeup();

            $remote = $this->subject->setMigrationPoint($this->detectMigrationPoint());

            $this->fire('tenant.migrated', ['identity' => $this->identity]);

        }

        return $this;

    }

    public function detectMigrationPoint() {
        //! NOTE: TODO: FIXME: è decisamente da rivedere, perché non trovo adeguata documentazione su Illuminate\Database\Migrations\MigrationRepositoryInterface
        // determino l'hash della più recente migration presente su questo database
        try {
            $migrationsList = $this->db->table('migrations')->orderBy('id', 'desc')->pluck('migration');
        }

        // catturo l'eventuale QueryException
        catch (QueryException $e) {

            if (!$e->getCode() === '42S02') {
                throw $e;
            }

            // se arrivo qui, allora vuol dire che non esiste la tabella cercata ('migrations'),
            // ragion per cui è SICURO che sul database corrente debba essere lanciato il migrate.
            return null;
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

        $this->fire('tenant.identity.creating', ['identity' => $identity]);

        if ($this->messenger->exists($identity)) {
            throw new TenantException('Tenant "' . $identity . '" already exists!');
        }

        $this->messenger->subject($identity);

        $pathway = $this->pathfinder->for([$identity]);

        $generateParams = [
            'now' => Carbon::now(),
            'pathway' => $pathway,
            'database' => $this->createDatabase($identity, $pathway)
        ];

        $hooks = $this->resolver->getHooks();

        foreach ($hooks as $hook) {
            $entry = $hook->getEntry();
            $package[$entry] = $hook->generate($generateParams);
        }

        $this->messenger->write($package, $identity);

        $this->fire('tenant.identity.created', ['identity' => $identity]);

        return $package;
    }

    public function createDatabase(string $identity, array $pathway, array $databaseServer = null) : array {

        /* TODO:
            Questa funzione dovrebbe richiamare un sistema esterno capace di
            creare, su uno specifico database server, un user ed un database,
            restituendo poi il package da scrivere.

            Per il momento "simula" l'output.
        */

        $this->fire('tenant.database.creating', ['identity' => $identity, 'databaseServer' => $databaseServer]);

        $credentials = [
            'driver' => $databaseServer['driver'] ?? 'mysql',
            'host' => $databaseServer['host'] ?? ('mariadb'.random_int(1,5).'.example.com'),
            'port' => $databaseServer['port'] ?? '3306',
            'database' => 'DB_' . strtoupper(join('_',$pathway['resourceId'])),
            'username' => 'user_' . strtoupper(array_last($pathway['normalized'])) . '_' . strtolower(str_random(4)),
            'password' => str_random(16),
        ];

        $this->fire('tenant.database.created', ['identity' => $identity, 'databaseServer' => $databaseServer]);

        return $credentials;

    }

}
