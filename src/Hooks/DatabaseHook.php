<?php

namespace AmcLab\Tenancy\Hooks;

use AmcLab\Disorder\Disorder;
use AmcLab\Tenancy\Abstracts\AbstractHook;
use AmcLab\Tenancy\Contracts\Hook as Contract;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

class DatabaseHook extends AbstractHook implements Contract {

    //protected $connection;
    protected $configRepository;
    protected $concreteParams;
    protected $resolver;

    public function __construct(ConfigRepository $configRepository) {
        //$this->connection = $connection;
        $this->configRepository = $configRepository;
        parent::__construct();
    }

    protected function concrete(array $config = [], array $concreteParams = []) {

        $this->concreteParams = $concreteParams['database'];
        $this->resolver = $concreteParams['database']['resolver'];

        $connection = $this->concreteParams['connection'];
        $autoconnect = $this->concreteParams['autoconnect'] ?? false;
        $makeDefault = $this->concreteParams['makeDefault'] ?? false;

        // prendo la configurazione dei database di Laravel
        $base = $this->configRepository->get('database');

        // scrivo i dati di connessione ricevuti sopra il template del driver corrente
        $newConfig = $config['package'] + $base['connections'][$config['package']['driver']];

        if ($autoconnect) {
            // chiudo quella che attualmente è la connessione di default
            //$this->connection->purge();
            $this->resolver->purge();
        }

        if ($makeDefault) {
            // setto il tipo di connessione ricevuta come connessione di default
            $this->configRepository->set('database.default', $connection);
        }

        // sovrascrivo i dati di connessione sul relativo record
        $this->configRepository->set('database.connections.' . $connection, $newConfig);

        if ($autoconnect) {
            // ristabilisco la connessione
            // return $this->connection->reconnect($connection);
            return $this->resolver->reconnect($connection);
        }
        else {
            // return $this->connection->connection($connection);
            return $this->resolver->connection($connection);
        }
    }

    public function destroy() {

        // chiudo quella che attualmente è la connessione di default
        if ($this->concreteParams['autoconnect'] ?? false) {
            //$this->connection->purge($this->concreteParams['connection']);
        }
        $this->resolver->purge($this->concreteParams['connection']);
        $this->resolver = null;

    }

    public function generate(array $generateParams = []) : array {

        $generatorService = [
            'package' => $generateParams['database'],
        ];

        return $generatorService;
    }

}
