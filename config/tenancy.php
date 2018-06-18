<?php

return [

    'package-store' => \AmcLab\KeymasterStore\Contracts\Messenger::class,

    'resolver' => [
        'hooks' => [
            \AmcLab\Tenancy\Hooks\DatabaseHook::class,
            \AmcLab\Tenancy\Hooks\EncryptionHook::class,
            \AmcLab\Tenancy\Hooks\MaskingHook::class,
            //...altri?
        ],
    ],

    'api' => [
        'database-manager' => [
            'uri' => '/api/amcManager',
            'key' => '123',
            'secret' => 'qwerty',
        ]
    ]

];
