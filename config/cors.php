<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'https://dsc-vite-react.vercel.app',
        'https://dsc-vite-react-p9qv111ui-kencha-a11s-projects.vercel.app',
    ],
    'allowed_origins_patterns' => [
        '/^https:\/\/dsc-vite-react.*\.vercel\.app$/',
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,

];
