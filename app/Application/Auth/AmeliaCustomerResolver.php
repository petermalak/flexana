<?php

namespace App\Application\Auth;

use App\Models\Customer;

/**
 * Resolves "Amelia" customer - now uses MySQL (Laravel) only.
 * WordPress connection is disabled; Laravel Customer is the source of truth.
 */
final class AmeliaCustomerResolver
{
    /**
     * Resolve customer for Amelia-related operations.
     * Returns the Laravel Customer (MySQL) - no WordPress lookup.
     */
    public function resolveAmeliaUser(Customer $laravelCustomer): ?Customer
    {
        return $laravelCustomer;
    }
}
