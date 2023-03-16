<?php

use OpenGemeenten\CacheHeaders\Middleware\CacheHeaders;

return [
    'frontend' => [
        'opengemeenten/cache-headers' => [
            'target' => CacheHeaders::class,
            'after' => [
                'typo3/cms-frontend/tsfe'
            ]
        ]
    ]
];
