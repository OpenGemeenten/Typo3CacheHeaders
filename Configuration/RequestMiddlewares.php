<?php

use OpenGemeenten\CacheHeaders\Middleware\CacheHeaders;

return [
    'frontend' => [
        'opengemeenten/cms-frontend/cache-headers' => [
            'target' => CacheHeaders::class,
            'after' => [
                'typo3/cms-frontend/tsfe'
            ],
            'before' => [
                'typo3/cms-frontend/prepare-tsfe-rendering'
            ]
        ]
    ]
];
