<?php

namespace AmcLab\Environment;

use AmcLab\Environment\Contracts\MigrationManager as Contract;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;

class MigrationManager implements Contract {

    protected $app;
    protected $cache;
    protected $kernel;
    protected $configRepository;
    protected $connection;

    public function __construct(Application $app, CacheRepository $cache, Kernel $kernel, Repository $configRepository) {
        $this->app = $app;
        $this->cache = $cache;
        $this->kernel = $kernel;
        $this->configRepository = $configRepository;
    }

    public function setConnection(ConnectionInterface $connection) {
        $this->connection = $connection;
        return $this;
    }

    public function unsetConnection() {
        $this->connection = null;
        return $this;
    }

    public function getAppStatus(Application $app = null) {
        $app = $app ?? $this->app;
        return $this->getStatusFromFilesystem($app);
    }

    public function install() {

        if ($this->getLocalStatus($this->connection)) {
            return;
        }

        $databaseConnectionName = $this->connection->getConfig()['name'];

        $this->kernel->call('migrate:install', [
            '--database' => $databaseConnectionName,
        ]);

    }

    public function attempt() {

        $databaseConnectionName = $this->connection->getConfig()['name'];

        $this->kernel->call('migrate', [
            '--force' => true,
            '--database' => $databaseConnectionName,
            '--path' => $this->configRepository->get('environment.migrations-path'),
        ]);

        return $this->getLocalStatus($this->connection);
    }

    protected function getStatusFromFilesystem($app) {

        return $this->cache->rememberForever('migration_status', function() use ($app) {
            $path = $app->databasePath() . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . 'tenants';
            $files = $app->make('migrator')->getMigrationFiles($path);
            return md5(array_last(array_keys($files), true));
            // NOTE: uso array_last perché getLocalStatus prende solo la più recente migration
            // (evito così di dover tirare un elenco lunghissimo ad ogni caricamento)
        });
    }

    public function getLocalStatus() {

        try {
            $last = md5($this->detectCurrentPoint($this->connection));
        }

        catch (QueryException $e) {
            if (!$e->getCode() === '42S02') {
                throw $e;
            }

            // se arrivi qui, vuol dire che non esiste la tabella "migrations"!
            $last = null;
        }

        return $last;

    }

    public function detectCurrentPoint() {
        return $this->connection->table('migrations')->orderBy('id', 'desc')->first()->migration ?? null;
    }

}
