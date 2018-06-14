<?php

namespace AmcLab\Tenancy\Services;

use Acquia\Hmac\Exception\MalformedResponseException;
use Acquia\Hmac\Guzzle\HmacAuthMiddleware;
use Acquia\Hmac\Key;
use AmcLab\Tenancy\Contracts\Services\LockerService as Contract;
use AmcLab\Tenancy\Exceptions\LockerServiceException;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;

class LockerService implements Contract {

    protected const ENDPOINT_URI_BASE = '/v1/tenancy';

    protected $client;
    protected $config;

    public function __construct(array $config, ClientInterface $client) {

        $this->config = $config;

        $this->client = $client;

    }

    public function getConfig() : array {
        return $this->config;
    }

    protected function makeUri(array $config) : string {

        return join('/', [
            self::ENDPOINT_URI_BASE,
            join(':', $config['resourceId'] ?? []),
            join('/', $config['sub'] ?? []),
        ]);

    }

    public function get(array $config) : array {

        if (!isset($config['resourceId'])) {
            throw new LockerServiceException("Missing 'resourceId'");
        }

        try {

            $externalStoreRequest = $this->client->get($this->makeUri($config));

        }

        catch(Exception $e) {

            if ($e instanceof MalformedResponseException || $e instanceof RequestException){
                throw new LockerServiceException($e->getResponse()->getReasonPhrase(), $config['resourceId'], $e->getResponse()->getStatusCode(), $e);
            }

            throw $e;

        }

        return json_decode($externalStoreRequest->getBody()->getContents(), true);

    }

    public function put(array $config, $payload) : array {

        if (!isset($config['resourceId'])) {
            throw new LockerServiceException("Missing 'resourceId'");
        }

        $params = [ RequestOptions::JSON => $payload ];

        try {

            $externalStoreRequest = $this->client->put($this->makeUri($config), $params);

        }

        catch(Exception $e) {

            if ($e instanceof MalformedResponseException || $e instanceof RequestException){
                throw new LockerServiceException($e->getResponse()->getReasonPhrase(), $config['resourceId'], $e->getResponse()->getStatusCode(), $e);
            }

            throw $e;

        }

        return json_decode($externalStoreRequest->getBody()->getContents(), true);

    }

    public function delete(array $config) : bool {

        if (!isset($config['resourceId'])) {
            throw new LockerServiceException("Missing 'resourceId'");
        }

        try {

            $externalStoreRequest = $this->client->delete($this->makeUri($config));

        }

        catch(Exception $e) {

            if ($e instanceof MalformedResponseException || $e instanceof RequestException){
                throw new LockerServiceException($e->getResponse()->getReasonPhrase(), $config['resourceId'], $e->getResponse()->getStatusCode(), $e);
            }

            throw $e;

        }

        return true;

    }

    public function post(array $config, $payload) : array {

        if (!isset($config['resourceId'])) {
            throw new LockerServiceException("Missing 'resourceId'");
        }

        $params = [ RequestOptions::JSON => $payload ];

        try {

            $externalStoreRequest = $this->client->post($this->makeUri($config), $params);

        }

        catch(Exception $e) {

            if ($e instanceof MalformedResponseException || $e instanceof RequestException){
                throw new LockerServiceException($e->getResponse()->getReasonPhrase(), $config['resourceId'], $e->getResponse()->getStatusCode(), $e);
            }

            throw $e;

        }

        return json_decode($externalStoreRequest->getBody()->getContents(), true);

    }

}
