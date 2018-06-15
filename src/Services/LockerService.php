<?php

namespace AmcLab\Tenancy\Services;

use Acquia\Hmac\Exception\MalformedResponseException;
use AmcLab\Tenancy\Contracts\Services\LockerService as Contract;
use AmcLab\Tenancy\Exceptions\LockerServiceException;
use AmcLab\Tenancy\Traits\HasConfigTrait;
use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Illuminate\Config\Repository;

class LockerService implements Contract {

    use HasConfigTrait;

    protected const ENDPOINT_URI_BASE = '/v1/tenancy';

    protected $client;

    public function __construct() {
    }

    public function setClient(ClientInterface $client) {
        $this->client = $client;
        return $this;
    }

    protected function makeUri(array $input) : string {

        return join('/', [
            self::ENDPOINT_URI_BASE,
            join(':', $input['resourceId'] ?? []),
            join('/', $input['sub'] ?? []),
        ]);

    }

    public function get(array $input) : array {

        if (!isset($input['resourceId'])) {
            throw new LockerServiceException("Missing 'resourceId'");
        }

        try {

            $externalStoreRequest = $this->client->get($this->makeUri($input));

        }

        catch(Exception $e) {

            if ($e instanceof MalformedResponseException || $e instanceof RequestException){

                throw new LockerServiceException(
                    (is_null($e->getResponse()) ? $e->getMessage() : $e->getResponse()->getReasonPhrase()),
                    $input['resourceId'],
                    (is_null($e->getResponse()) ? $e->getCode() : $e->getResponse()->getStatusCode()),
                    $e);
            }

            throw $e;

        }

        return json_decode($externalStoreRequest->getBody()->getContents(), true);

    }

    public function put(array $input, $payload) : array {

        if (!isset($input['resourceId'])) {
            throw new LockerServiceException("Missing 'resourceId'");
        }

        $params = [ RequestOptions::JSON => $payload ];

        try {

            $externalStoreRequest = $this->client->put($this->makeUri($input), $params);

        }

        catch(Exception $e) {
            if ($e instanceof MalformedResponseException || $e instanceof RequestException){
                throw new LockerServiceException($e->getResponse()->getReasonPhrase(), $input['resourceId'], $e->getResponse()->getStatusCode(), $e);
            }

            throw $e;

        }

        return json_decode($externalStoreRequest->getBody()->getContents(), true);

    }

    public function delete(array $input) : bool {

        if (!isset($input['resourceId'])) {
            throw new LockerServiceException("Missing 'resourceId'");
        }

        try {

            $externalStoreRequest = $this->client->delete($this->makeUri($input));

        }

        catch(Exception $e) {

            if ($e instanceof MalformedResponseException || $e instanceof RequestException){
                throw new LockerServiceException($e->getResponse()->getReasonPhrase(), $input['resourceId'], $e->getResponse()->getStatusCode(), $e);
            }

            throw $e;

        }

        return true;

    }

    public function post(array $input, $payload) : array {

        if (!isset($input['resourceId'])) {
            throw new LockerServiceException("Missing 'resourceId'");
        }

        $params = [ RequestOptions::JSON => $payload ];

        try {

            $externalStoreRequest = $this->client->post($this->makeUri($input), $params);

        }

        catch(Exception $e) {

            if ($e instanceof MalformedResponseException || $e instanceof RequestException){
                throw new LockerServiceException($e->getResponse()->getReasonPhrase(), $input['resourceId'], $e->getResponse()->getStatusCode(), $e);
            }

            throw $e;

        }

        return json_decode($externalStoreRequest->getBody()->getContents(), true);

    }

}
