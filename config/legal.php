<?php

// Operator identity for the legal notice and privacy policy. All personal
// data comes from the environment so it never lives in the repository.
return [

    'operator' => [
        'name' => env('LEGAL_OPERATOR_NAME', ''),
        'street' => env('LEGAL_OPERATOR_STREET', ''),
        'zip' => env('LEGAL_OPERATOR_ZIP', ''),
        'city' => env('LEGAL_OPERATOR_CITY', ''),
        'country' => env('LEGAL_OPERATOR_COUNTRY', 'Germany'),
        'email' => env('LEGAL_CONTACT_EMAIL', ''),
        'phone' => env('LEGAL_CONTACT_PHONE'),
    ],

    // Falls back to the operator name when unset.
    'responsible_name' => env('LEGAL_RESPONSIBLE_NAME'),

    'vat_id' => env('LEGAL_VAT_ID'),

    // Shows the § 19 UStG small-business note when true.
    'small_business' => (bool) env('LEGAL_SMALL_BUSINESS', true),

];
