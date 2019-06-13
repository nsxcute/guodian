<?php

return [
    'redis1' => [
        'hostname'        => '127.0.0.1',
        'hostport'        => '6379',
        // redis 键名
        'keys'=>[
            // 可视化所有api
            'openvApisRedisKey'=>'2019openvApisRedisKey',
            'apiRedisKey'=>'2019apiRedisKey',
        ]
    ],
];