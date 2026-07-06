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

    /*
    | Used when splitting E.164 numbers into countryCode + phoneNumber for API responses.
    | Longest matching prefix wins (e.g. 44 before 4).
    */
    'known_country_calling_codes' => [
        '971', '966', '965', '974', '973', '972', '968', '962', '961', '880',
        '886', '852', '853', '855', '856', '880', '886', '90', '91', '92', '93', '94', '95', '98',
        '20', '27', '30', '31', '32', '33', '34', '36', '39', '40', '41', '43', '44', '45', '46',
        '47', '48', '49', '51', '52', '53', '54', '55', '56', '57', '58', '60', '61', '62', '63',
        '64', '65', '66', '81', '82', '84', '86', '1',
    ],

];
