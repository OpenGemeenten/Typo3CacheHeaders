<?php
$EM_CONF['opengemeenten_cacheheaders'] = [
    'title' => 'OpenGemeenten cache headers',
    'description' => 'Frontend caching modifications with ETag',
    'category' => 'fe',
    'version' => '2.0.0',
    'state' => 'stable',
    'author' => 'OpenGemeenten',
    'author_email' => 'hallo@opengemeenten.nl',
    'author_company' => 'OpenGemeenten',
    'constraints' => [
        'depends' => [
            'frontend' => '10.4.0-11.5.99'
        ],
        'conflicts' => [],
        'suggests' => []
    ]
];
