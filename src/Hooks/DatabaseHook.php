<?php

namespace AmcLab\Environment\Hooks;

use AmcLab\Disorder\Disorder;
use AmcLab\Environment\Abstracts\AbstractHook;
use AmcLab\Environment\Contracts\Hook as Contract;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

class DatabaseHook extends AbstractHook implements Contract {

    //protected $connection;
    protected $configRepository;
    protected $concreteParams;
    protected $databaseConnector;

    public function __construct(ConfigRepository $configRepository) {
        $this->configRepository = $configRepository;
        parent::__construct();
    }

    protected function concrete(array $config = [], array $concreteParams = []) {

        $this->concreteParams = $concreteParams['database'];
        $this->databaseConnector = $concreteParams['database']['connector'];

        $connection = $this->concreteParams['connection'];
        $autoconnect = $this->concreteParams['autoconnect'] ?? false;
        $makeDefault = $this->concreteParams['makeDefault'] ?? false;

        // prendo la configurazione dei database di Laravel
        $connectionsConfig = $this->configRepository->get('database.connections');
        $currentHookConfig = $this->configRepository->get('environment.resolver.hooks.' . get_class($this));

        // scrivo i dati di connessione ricevuti sopra il template del driver corrente
        $newConfig = $config['package'] + $connectionsConfig[$currentHookConfig['connectionName']];

        if ($autoconnect) {
            // chiudo quella che attualmente è la connessione di default
            $this->databaseConnector->purge();
        }

        if ($makeDefault) {
            // setto il tipo di connessione ricevuta come connessione di default
            $this->configRepository->set('database.default', $connection);
        }

        // sovrascrivo i dati di connessione sul relativo record
        $this->configRepository->set('database.connections.' . $connection, $newConfig);

        // restituisco la connessione usata dall'hook
        if ($autoconnect) {
            return $this->databaseConnector->reconnect($connection);
        }
        else {
            return $this->databaseConnector->connection($connection);
        }
    }

    public function destroy() {

        $this->databaseConnector->purge($this->concreteParams['connection']);

    }

    public function generate(array $generateParams = []) : array {

        $generatorService = [
            'package' => $generateParams['database'],
        ];

        return $generatorService;
    }

}
