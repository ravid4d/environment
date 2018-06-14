<?php

namespace AmcLab\Tenancy\Hooks;

use AmcLab\Disorder\Disorder;
use AmcLab\Tenancy\Abstracts\AbstractHook;
use AmcLab\Tenancy\Contracts\Hook as Contract;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

class DatabaseHook extends AbstractHook implements Contract {

    protected $connection;
    protected $configRepository;
    protected $concreteParams;

    public function __construct(\Illuminate\Database\DatabaseManager $connection, ConfigRepository $configRepository) {
        $this->connection = $connection;
        $this->configRepository = $configRepository;
        parent::__construct();
    }

    protected function concrete(array $config = [], array $concreteParams = []) {

        $this->concreteParams = $concreteParams['database'];

        $connection = $this->concreteParams['connection'];
        $autoconnect = $this->concreteParams['autoconnect'] ?? false;
        $makeDefault = $this->concreteParams['makeDefault'] ?? false;

        // prendo la configurazione dei database di Laravel
        $base = $this->configRepository->get('database');

        // scrivo i dati di connessione ricevuti sopra il template del driver corrente
        $newConfig = $config['package'] + $base['connections'][$config['package']['driver']];

        if ($autoconnect) {
            // chiudo quella che attualmente è la connessione di default
            $this->connection->purge();
        }

        if ($makeDefault) {
            // setto il tipo di connessione ricevuta come connessione di default
            $this->configRepository->set('database.default', $connection);
        }

        // sovrascrivo i dati di connessione sul relativo record
        $this->configRepository->set('database.connections.' . $connection, $newConfig);

        if ($autoconnect) {
            // ristabilisco la connessione
            return $this->connection->reconnect($connection);
        }
        else {
            return $this->connection->connection($connection);
        }
    }

    public function destroy() {

        // prendo la configurazione dei database di Laravel
        //$base = $this->configRepository->get('database');

        // chiudo quella che attualmente è la connessione di default
        if ($this->concreteParams['autoconnect'] ?? false) {
            // $this->connection->purge($base['default']);
            $this->connection->purge($this->concreteParams['connection']);
        }

        // // REVIEW: elimino la password dalla config. serve davvero eliminare i valori dalla config?
        // $this->configRepository->set('database.connections.' . $base['default'] . '.password', null);

    }

    public function generate(array $generateParams = []) : array {

        $generatorService = [
            'package' => $generateParams['database'],
        ];

        return $generatorService;
    }

}
