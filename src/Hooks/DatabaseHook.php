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
    protected $connectionResolver;

    public function __construct(ConfigRepository $configRepository) {
        $this->configRepository = $configRepository;
        parent::__construct();
    }

    protected function concrete(array $config = [], array $concreteParams = []) {

        $this->concreteParams = $concreteParams['database'];
        $this->connectionResolver = $concreteParams['database']['resolver'];

        $connection = $this->concreteParams['connection'];
        $autoconnect = $this->concreteParams['autoconnect'] ?? false;
        $makeDefault = $this->concreteParams['makeDefault'] ?? false;

        // prendo la configurazione dei database di Laravel
        $base = $this->configRepository->get('database');

        // scrivo i dati di connessione ricevuti sopra il template del driver corrente
        $newConfig = $config['package'] + $base['connections'][$config['package']['driver']];

        if ($autoconnect) {
            // chiudo quella che attualmente è la connessione di default
            $this->connectionResolver->purge();
        }

        if ($makeDefault) {
            // setto il tipo di connessione ricevuta come connessione di default
            $this->configRepository->set('database.default', $connection);
        }

        // sovrascrivo i dati di connessione sul relativo record
        $this->configRepository->set('database.connections.' . $connection, $newConfig);

        // restituisco la connessione usata dall'hook
        if ($autoconnect) {
            return $this->connectionResolver->reconnect($connection);
        }
        else {
            return $this->connectionResolver->connection($connection);
        }
    }

    public function destroy() {

        $this->connectionResolver->purge($this->concreteParams['connection']);

    }

    public function generate(array $generateParams = []) : array {

        $generatorService = [
            'package' => $generateParams['database'],
        ];

        return $generatorService;
    }

}
