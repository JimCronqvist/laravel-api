<?php

return [
    'frontend_url' => env('SSO_FRONTEND_URL', '/'),
    'frontend_exchange_flow_path' => env('SSO_FRONTEND_EXCHANGE_FLOW_PATH', '/auth/sso/exchange'),
    'use_exchange_flow' => env('SSO_USE_EXCHANGE_FLOW', false),
];
