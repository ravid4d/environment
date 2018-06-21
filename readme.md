# AmcLab\Tenancy

Gestione multi-tenant per i nuovi progetti AMC basati su Laravel.

...Work in progress...

## Todo

- aggiungere altri eventi ed implementare classi eventi

## Requisiti

## Installazione

## Documentazione


### Tenancy facade

Questa facade rappresenta un punto di accesso al Tenant di default.
I metodi eseguono dei compiti specifici dell'istanza Tenant, ma limitati ad una singola istanza di quest'ultima, che non cambia mai.

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
Non esistono vincoli, controlli o restrizioni su cosa possa essere aggiornato o aggiunto, ma le seguenti chiavi non possono essere modificate:

- database
- encryption
- masking

Se fosse necessario fare un update di questi valori, bisognerà usare gli appositi metodi del PackageStore legati al Tenant.

Per eliminare una proprietà dal package, è necessario settare a il suo valore a ```null```.

#### Helpers

Ogni Tenant ha, legate a sé stesso, un certo numero di classi esterne che vengono istanziate usando i parametri dello stesso Tenant (es. connessione al database, chiave di cifratura personale, ecc...).

È possibile accedere alle suddette istanze mediante i seguenti metodi helper:

- useDatabase()
- useEncryption()
- useMasking()

Ad esempio, sarà possibile eseguire il crypt di una stringa, usando l'encrypter del Tenant corrente, semplicemente scrivendo:

```php
Tenancy::useEncryption()->encrypt('qualcosa')
```

L'output sarà una stringa cifrata che sarà decifrabile _soltanto_ al Tenant corrente.


## Note importanti

Dopo un deploy, qualora siano presenti nuove migrations, è necessario eseguire:

```bash
php artisan cache:forget migration_status
```

