<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Single Company Configuration
    |--------------------------------------------------------------------------
    |
    | NSDCRM / BuildFlow is configured exclusively for NSD Earth Movers &
    | Construction Ltd. Multi-tenant SaaS workspace switching and onboarding
    | are disabled.
    |
    */

    'single_company_mode' => env('SINGLE_COMPANY_MODE', true),

    'default_slug' => env('DEFAULT_COMPANY_SLUG', 'nsd-earth-movers'),

    'default_name' => env('DEFAULT_COMPANY_NAME', 'NSD Earth Movers & Construction Ltd'),

    'default_legal_name' => env('DEFAULT_COMPANY_LEGAL_NAME', 'NSD Earth Movers & Infrastructure Private Limited'),

    'default_gstin' => env('DEFAULT_COMPANY_GSTIN', '29AAACN1234F1Z5'),

    'default_pan' => env('DEFAULT_COMPANY_PAN', 'AAACN1234F'),
];
