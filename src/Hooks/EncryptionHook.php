<?php

namespace AmcLab\Tenancy\Hooks;

use AmcLab\Tenancy\Abstracts\AbstractHook;
use AmcLab\Tenancy\Contracts\Hook as Contract;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Encryption\Encrypter as LaravelEncrypterImplementation;

class EncryptionHook extends AbstractHook implements Contract {

    protected $encrypter;
    protected $configRepository;

    public function __construct(Encrypter $encrypter, ConfigRepository $configRepository) {
        $this->encrypter = $encrypter;
        $this->configRepository = $configRepository;
    }

    protected function concrete(array $config = [], array $concreteParams = []) {

        return unserialize($config['serialized']);

    }

    public function generate(array $generateParams = []) : array {

        $encrypterClass = get_class($this->encrypter);
        $cipher = $this->configRepository->get('app.cipher');
        $key = $this->encrypter->generateKey($cipher);

        $instance = new $encrypterClass($key, $cipher);

        return ['serialized' => serialize($instance)];

    }

}
