<?php

return [
    'discovery' => [
        'paths' => [
            app_path('Ai/Skills'),
        ],
        'namespaces' => [
            'App\\Ai\\Skills',
        ],
    ],

    'cache' => [
        'enabled' => env('AI_SKILLS_CACHE', app()->isProduction()),
        'store' => env('AI_SKILLS_CACHE_STORE', null),
        'ttl' => env('AI_SKILLS_CACHE_TTL', 3600),
        'discovery_ttl' => env('AI_SKILLS_DISCOVERY_TTL', 86400),
        'prefix' => env('AI_SKILLS_CACHE_PREFIX', 'ai-skills'),
    ],
];
