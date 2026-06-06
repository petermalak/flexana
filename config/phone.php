<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default country (Egypt) for local numbers entered with a leading 0
    |--------------------------------------------------------------------------
    |
    | Only numbers matching default_country_local_patterns are rewritten to
    | +{default_country_code}…. International numbers must use +country code
    | (e.g. +447911123456). A UK-style 07… number is not auto-converted.
    |
    */

    'default_country_code' => env('PHONE_DEFAULT_COUNTRY_CODE', '20'),

    'default_country_local_patterns' => [
        '/^01[0125]\d{8}$/', // Egyptian mobile
        '/^02\d{8}$/',       // Cairo landline
        '/^03\d{8}$/',       // Alexandria landline
        '/^04\d{8}$/',
        '/^05\d{8}$/',
        '/^06\d{8}$/',
        '/^08\d{8}$/',
        '/^09\d{8}$/',
    ],

];
