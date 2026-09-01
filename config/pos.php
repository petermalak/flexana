<?php

return [
    'invoice_prefix' => env('POS_INVOICE_PREFIX', 'FLX'),

    // Flexana does not currently levy separate tax or service charges; POS can treat these as 0.
    'default_tax_amount' => (float) env('POS_DEFAULT_TAX_AMOUNT', 0),
    'default_service_charge' => (float) env('POS_DEFAULT_SERVICE_CHARGE', 0),

    'max_per_page' => (int) env('POS_MAX_PER_PAGE', 500),
];
