<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_origins' => ['*'],      // en dev es lo más simple
    'allowed_methods' => ['*'],
    'allowed_headers' => ['*'],
    'supports_credentials' => false, // usamos tokens, no cookies

];
