<?php

return [
    'rabbitmq1' => [
        'hostname'        => '127.0.0.1',
        'hostport'        => '5672',
        'username' => 'guest',
        'password' => 'guest',
        // redis 键名
        'keys'=>[
            // 可视化所有api
            'openvApisRabbitKey'=>'2019openvApisRabbitKey'
        ]
    ],
];