# AmcLab\Environment

Gestione ambiente multi-tenant per i nuovi progetti AMC basati su Laravel versione >= 5.6.

...Work in progress...

## Todo

- aggiungere altri eventi ed implementare classi eventi
- valutare generatore di database basato su classe messenger (sostituire dominio "tenant" con "server")
- documentare Scope
- documentare metodi getSpecs() e setWithSpecs()

## Requisiti

## Installazione

## Documentazione


### Environment facade

Questa facade rappresenta un punto di accesso all'ambiente corrente.
I metodi eseguono dei compiti specifici dell'istanza Environment, ma limitati ad una singola istanza di quest'ultima, che non cambia mai.

```php
public function getTenant() : Tenant
```
Restituisce l'istanza Tenant corrente.

```php
public function setIdentity(string $identity)
```
Setta l'identità corrente a quella di uno specifico cliente.

```php
public function unsetIdentity()
```
Sgancia l'identità associata per poterne agganciare un'altra.

```php
public function getIdentity() :? string
```
Restituisce l'identità corrente.

```php
public function createIdentity(string $newIdentity, $databaseServer ```= [])
```

Crea un nuovo database e lo collega ad una nuova identità, dopodiché setta quest'ultima come corrente.

```php
public function update(array $customPackage)
```
Aggiorna il package del cliente corrente.
Non esistono vincoli, controlli o restrizioni su cosa possa essere aggiornato o aggiunto, ma le seguenti chiavi, una volta settate, *non possono essere modificate* da questo metodo:

- database
- encryption
- masking

Se fosse necessario fare un update di questi valori, bisognerà usare gli appositi metodi del PackageStore legati al Tenant.

Per eliminare una proprietà dal package, è necessario settare a il suo valore a ```null```.

```php
public function updateAndReset(array $customPackage)
```
Effettua l'update come sopra e resetta l'identità al termine.

#### Helpers

Ogni Tenant ha, legate a sé stesso, un certo numero di classi esterne che vengono istanziate usando i parametri dello stesso Tenant (es. connessione al database, chiave di cifratura personale, ecc...).

È possibile accedere alle suddette istanze mediante i seguenti metodi helper:

- useDatabase()
- useEncryption()
- useMasking()

Ad esempio, sarà possibile eseguire il crypt di una stringa, usando l'encrypter del Tenant corrente, semplicemente scrivendo:

```php
Environment::useEncryption()->encrypt('qualcosa')
```

L'output sarà una stringa cifrata che sarà decifrabile _soltanto_ al Tenant corrente.


## Note importanti

Dopo un deploy, qualora siano presenti nuove migrations, è necessario eseguire:

```bash
php artisan cache:forget migration_status
```

Se si effettuano modifiche al Pathfinder o al suo output, è necessario cancellarne la cache!!

Le eccezioni che originano a seguito di un errore di connessione tra web services, restituiscono uno status compatibile con gli status HTTP, per facilitarne l'identificazione.

Le altre eccezioni, dovrebbero quasi sempre restituire uno status >= 1000:

- 1000: la dipendenza di riferimento o una delle dipendenze richieste deve essere istanziata per procedere
- 1001: la dipendenza è già stata istanziata, quindi non può essere riistanziata
- 1000 + http_equivalent: rappresenta un errore "simile" per contesto all'equivalente HTTP (ad es.: 1409 per indicare che si è verificato un conflitto.) - **Questi codici potrebbero variare!**






