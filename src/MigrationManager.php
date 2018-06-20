<?php

namespace AmcLab\Tenancy;

use AmcLab\Tenancy\Contracts\MigrationManager as Contract;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;

class MigrationManager implements Contract {

    protected $app;
    protected $appCache;
    protected $kernel;

    public function __construct(Application $app, CacheRepository $appCache, Kernel $kernel) {
        $this->app = $app;
        $this->appCache = $appCache;
        $this->kernel = $kernel;
    }

    public function getAppStatus(Application $app = null) {
        $app = $app ?? $this->app;
        return $this->getStatusFromFilesystem($app);
    }

    protected function getStatusFromFilesystem($app) {
        $path = $app->databasePath() . DIRECTORY_SEPARATOR . 'migrations';
        $files = $app->make('migrator')->getMigrationFiles($path);
        return md5(array_last(array_keys($files)));
    }

    public function getLocalStatus(ConnectionInterface $localConnection) {

        // TODO: AGGIUNGERE CACHE!!!!

        try {
            $last = md5($this->detectCurrentPoint($localConnection));
        }

        catch (QueryException $e) {
            if (!$e->getCode() === '42S02') {
                throw $e;
            }
            $last = null;
        }

        return $last;

    }

    public function detectCurrentPoint(ConnectionInterface $localConnection) {
        return $localConnection->table('migrations')->orderBy('id', 'desc')->first()->migration ?? null;
    }

    public function install(ConnectionInterface $localConnection) {

        if ($this->getLocalStatus($localConnection)) {
            return;
        }

        $databaseConnectionName = $localConnection->getConfig()['name'];

        $this->kernel->call('migrate:install', [
            '--database' => $databaseConnectionName,
        ]);

    }

    public function attempt(ConnectionInterface $localConnection) {

        $databaseConnectionName = $localConnection->getConfig()['name'];

        $this->kernel->call('migrate', [
            '--force' => true,
            '--database' => $databaseConnectionName,
        ]);

        return $this->getLocalStatus($localConnection);
    }


}
