<?php

return [

    'package-store' => \AmcLab\KeymasterStore\Contracts\Messenger::class,

    'resolver' => [
        'hooks' => [
            \AmcLab\Environment\Hooks\DatabaseHook::class,
            \AmcLab\Environment\Hooks\EncryptionHook::class,
            \AmcLab\Environment\Hooks\MaskingHook::class,
            //...altri?
        ],
    ],

    'api' => [
        'database-manager' => [ // TODO: da implementare...
            'uri' => '/api/amcManager',
            'key' => '123',
            'secret' => 'qwerty',
        ]
    ],

    'migrations-path' => 'database/migrations/tenants',

];
