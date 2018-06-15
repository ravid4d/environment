<?php

namespace AmcLab\Tenancy;

use AmcLab\Tenancy\Contracts\Messenger as Contract;
use AmcLab\Tenancy\Contracts\Pathfinder;
use AmcLab\Tenancy\Contracts\Services\ConciergeService;
use AmcLab\Tenancy\Contracts\Services\LockerService;
use AmcLab\Tenancy\Exceptions\LockerServiceException;
use AmcLab\Tenancy\Exceptions\MessengerException;
use AmcLab\Tenancy\Traits\HasConfigTrait;
use AmcLab\Tenancy\Traits\HasEventsDispatcherTrait;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Events\Dispatcher;

class Messenger implements Contract {

    use HasConfigTrait;
    use HasEventsDispatcherTrait;

    protected $cache;
    protected $encrypter;
    protected $config;

    protected $remember;
    protected $production;
    protected $shortcuts;

    protected $conciergeService;
    protected $lockerService;
    protected $pathfinder;

    protected static $wrapped = [
        'id' => null,
        'instance' => null,
    ];

    /**
     * Il Messenger è l'intermediario capace di comunicare con l'entità che fisicamente archivia
     * i dati dei Tenants (LockerService) e di inviarle richieste.
     *
     * I dati dei Tenants sono cifrati mediante l'Encrypter di Laravel usando una chiave sconosciuta
     * al LockerService, la cui gestione è demandata ad un'entità apposita (ConciergeService), che è capace di
     * generare chiavi hash semplici (per l'indicizzazione) o complesse (per la cifratura), univoche per
     * ciascun Tenant.
     *
     * @param Cache $cache
     * @param float $remember
     * @param boolean $production
     * @param array $shortcuts
     */
    public function __construct(ConciergeService $conciergeService, LockerService $lockerService, Encrypter $encrypter, Dispatcher $events) {

        $this->conciergeService = $conciergeService;
        $this->lockerService = $lockerService;
        $this->encrypter = $encrypter;
        $this->setEventsDispatcher($events);

    }

    public function setCacheRepository(Cache $cache) {
        $this->cache = $cache;
        return $this;
    }

    public function setShortcuts(array $shortcuts) : Contract {
        $this->shortcuts = $shortcuts;
        return $this;
    }

    public function setPathfinder(Pathfinder $pathfinder) : Contract {
        $this->pathfinder = $pathfinder;
        return $this;
    }

    public function usePathfinderFor(array $breadcrumbs) : array {
        if ($this->shortcuts) {
            if ($breadcrumbs) {
                throw new MessengerException('Re-routing is not allowed');
            }
            return $this->shortcuts;
        }

        $path = $this->pathfinder->for($breadcrumbs);

        return $path + ['uid' => $this->conciergeService->generate('short', $path['hashable'])];

    }

    /**
     * Restituisce una scorciatoia alla stessa entità, con riferimento ad una specifica
     * risorsa, per poter usare una sintassi più concisa (autopopolando il parametro ...$breadcrumbs
     * delle chiamate ai metodi da qui in poi concatenati).
     *
     * $wrapped = $messenger->subject(['AMC','123456'])
     * if ($wrapped->exists()) {
     *     return $wrapped->read();
     * }
     * else {
     *     echo "non esistente..."
     * }
     *
     * @param string|array ...$breadcrumbs
     * @return Contract
     */
    public function subject(...$breadcrumbs) : Contract {

        if ($this->shortcuts) {
            throw new MessengerException('Recursion from '.json_encode($this->shortcuts['breadcrumbs']).' to '.json_encode($breadcrumbs));
        }

        $shortcuts = $this->usePathfinderFor($breadcrumbs);
        $id = $shortcuts['uid'];

        $staticId = &self::$wrapped['id'];
        $staticInstance = &self::$wrapped['instance'];

        if (!($id === $staticId)) {
            $staticId = $id;
            $staticInstance = (clone $this)->setShortcuts($shortcuts);
        }

        return $staticInstance;

    }

    public function getConciergeService() {
        return $this->conciergeService;
    }
    public function getLockerService() {
        return $this->lockerService;
    }
    public function getPathfinder() {
        return $this->pathfinder;
    }
    public function getShortcuts() {
        return $this->shortcuts;
    }
    public function getBreadcrumbs() {
        return $this->breadcrumbs;
    }

    /**
     * Verifica l'esistenza di uno specifica risorsa
     *
     * @param string|array ...$breadcrumbs
     * @return boolean
     */
    public function exists(...$breadcrumbs) : bool {

        $shortcuts = $this->usePathfinderFor($breadcrumbs);

        try {
            $body = $this->lockerService->get(['resourceId' => $shortcuts['resourceId']]);
        }

        catch(LockerServiceException $e) {
            if ($e->getCode() === 404) {
                return false;
            }
            throw $e;
        }

        return true;
    }

    /**
     * Legge i dati di una specifica risorsa
     *
     * @param string|array ...$breadcrumbs
     * @return array
     */
    public function read(...$breadcrumbs) : array {

        $shortcuts = $this->usePathfinderFor($breadcrumbs);

        // legge dalla cache di uid o vi scrive il record ottenuto dal lockerService
        $retained = $this->cache->remember($shortcuts['uid'], $this->config['cache']['remember'], function () use ($shortcuts) {
            $body = $this->lockerService->get(['resourceId' => $shortcuts['resourceId']]);
            return $this->retain($body, $shortcuts['hashable']);
        });

        // decodifica il record
        $disclosed = $this->disclose($retained, $shortcuts['hashable']);

        return [
            'uid' => $shortcuts['uid'],
            'disclosed' => $disclosed,
            'migration' => $retained['payload']['migration'],
            'active' => !!$retained['payload']['active'],
        ];

    }

    public function readPackage(...$breadcrumbs) : array {

        $shortcuts = $this->usePathfinderFor($breadcrumbs);

        // legge dalla cache di uid o vi scrive il record ottenuto dal lockerService
        $retained = $this->cache->remember($shortcuts['uid'], $this->config['cache']['remember'], function () use ($shortcuts) {
            $body = $this->lockerService->get(['resourceId' => $shortcuts['resourceId']]);
            return $this->retain($body, $shortcuts['hashable']);
        });

        // decodifica il record
        return $disclosed = $this->disclose($retained, $shortcuts['hashable']);

    }

    /**
     * Scrive in una specifica risorsa
     *
     * @param mixed $package
     * @param string|array ...$breadcrumbs
     * @return array
     */
    public function write($package = null, ...$breadcrumbs) : array {

        $shortcuts = $this->usePathfinderFor($breadcrumbs);

        // cifra il dato e lo cancella dalla cache, cosicché le successive richieste siano forzate
        // a richiederne una copia aggiornata
        $encrypted = $this->encrypter($shortcuts['hashable'])->encrypt($package);

        $body = $this->lockerService->put(['resourceId' => $shortcuts['resourceId']], ['package' => $encrypted]);

        // IMPORTANT: aggiorno la cache attuale del tenant!
        // TODO: eliminare questo blocco e spostarlo in un metodo a parte per non creare ridondanza
        $this->cache->forget($shortcuts['uid']);
        $retained = $this->cache->remember($shortcuts['uid'], $this->config['cache']['remember'], function () use ($body, $shortcuts) {
            return $this->retain($body, $shortcuts['hashable']);
        });

        // decodifica il record
        $disclosed = $this->disclose($retained, $shortcuts['hashable']);

        return [
            'uid' => $shortcuts['uid'],
            'disclosed' => $disclosed,
            'migration' => $retained['payload']['migration'],
            'active' => !!$retained['payload']['active'],
        ];

    }

    /**
     * Elimina una specifica risorsa
     *
     * @param string|array ...$breadcrumbs
     * @return boolean
     */
    public function delete(...$breadcrumbs) : bool {

        $shortcuts = $this->usePathfinderFor($breadcrumbs);
        $this->lockerService->delete(['resourceId' => $shortcuts['resourceId']]);

        // IMPORTANT: cancello la cache attuale del tenant!
        $this->cache->forget($shortcuts['uid']);
        return true;

    }

    /**
     * Effettua la sospensione di una specifica risorsa (es. per mettere in manutenzione un
     * Tenant bloccandone l'accesso al di fuori della CLI)
     *
     * @param string|array ...$breadcrumbs
     * @return boolean
     */
    public function suspend(...$breadcrumbs) : bool {

        $shortcuts = $this->usePathfinderFor($breadcrumbs);
        $action = $this->lockerService->post(['resourceId' => $shortcuts['resourceId'], 'sub' => ['unsetActive']], null);

        // IMPORTANT: cancello la cache attuale del tenant!
        $this->cache->forget($shortcuts['uid']);

        return true;

    }

    /**
     * Effettua la riabilitazione di una specifica risorsa
     *
     * @param string|array ...$breadcrumbs
     * @return boolean
     */
    public function wakeup(...$breadcrumbs) : bool {

        $shortcuts = $this->usePathfinderFor($breadcrumbs);
        $action = $this->lockerService->post(['resourceId' => $shortcuts['resourceId'], 'sub' => ['setActive']], null);

        // IMPORTANT: cancello la cache attuale del tenant!
        $this->cache->forget($shortcuts['uid']);

        return true;

    }

    public function setMigrationPoint(?string $migration, ...$breadcrumbs) : bool {

        $shortcuts = $this->usePathfinderFor($breadcrumbs);
        $action = $this->lockerService->post(['resourceId' => $shortcuts['resourceId'], 'sub' => ['setMigration']], $migration);

        // IMPORTANT: cancello la cache attuale del tenant!
        $this->cache->forget($shortcuts['uid']);

        return true;

    }

    /**
     * Helper per unire un array dall'indice 'chains' con un secondo array
     *
     * @param string $name
     * @param array $breadcrumbs
     * @return void
     */
    protected function mergeChain(string $name, array $breadcrumbs) {

        $chain = $this->lockerService->getConfig()['chains'][$name];
        return array_merge($chain, $breadcrumbs);

    }
    /**
     * Restituisce il dato nella forma in cui deve essere scritto nella cache
     *
     * @param mixed $payload
     * @param array $hashable
     * @return void
     */
    protected function retain($payload, array $hashable) {

        // NOTE: se $this->config['cache']['production'] è false, la cache conterrà i dati IN CHIARO!
        if (!$this->config['cache']['production']) {
            $payload = $this->encrypter($hashable)->decrypt($payload);
        }

        return [
            'encoded' => $this->config['cache']['production'],
            'payload' => $payload,
        ];

    }

    /**
     * Restituisce il contenuto della cache, decifrandolo se necessario
     *
     * @param array $original
     * @param array $hashable
     * @return void
     */
    protected function disclose(array $original, array $hashable) {

        $disclosed = $original['payload']['contents']['package'];
        return $original['encoded'] ? $this->encrypter($hashable)->decrypt($disclosed) : $disclosed;

    }

    /**
     * Restituisce un'istanza del Laravel Encrypter "saltata" con una chiave privata ed esclusiva
     * del Tenant
     *
     * @param array $hashable
     * @return void
     */
    protected function encrypter(array $hashable) {

        $encrypterClass = get_class($this->encrypter);
        $key = $this->conciergeService->generate('generic', $hashable);
        return (new $encrypterClass($key, 'AES-256-CBC'));

    }

}
