<?php

namespace App\Application\Auth;

use App\Infrastructure\Persistence\Eloquent\AmeliaUserModel;
use App\Models\Customer;
use Illuminate\Support\Carbon;

final class AmeliaCustomerResolver
{
    /**
     * Resolve Amelia user from Laravel customer (use amelia_user_id or find/create by phone/email).
     */
    public function resolveAmeliaUser(Customer $laravelCustomer): ?AmeliaUserModel
    {
        if ($laravelCustomer->amelia_user_id) {
            $user = AmeliaUserModel::on('wordpress')
                ->where('type', 'customer')
                ->find($laravelCustomer->amelia_user_id);
            if ($user) {
                return $user;
            }
        }

        $ameliaUser = null;
        if (! empty($laravelCustomer->phone)) {
            $ameliaUser = AmeliaUserModel::on('wordpress')
                ->where('type', 'customer')
                ->where('phone', $laravelCustomer->phone)
                ->first();
        }
        if (! $ameliaUser && ! empty($laravelCustomer->email)) {
            $ameliaUser = AmeliaUserModel::on('wordpress')
                ->where('type', 'customer')
                ->where('email', $laravelCustomer->email)
                ->first();
        }

        if ($ameliaUser) {
            $laravelCustomer->amelia_user_id = $ameliaUser->id;
            $laravelCustomer->save();

            return $ameliaUser;
        }

        try {
            $ameliaUser = AmeliaUserModel::on('wordpress')->create([
                'firstName' => $laravelCustomer->first_name ?? 'Customer',
                'lastName' => $laravelCustomer->last_name ?? null,
                'email' => $laravelCustomer->email ?? null,
                'phone' => $laravelCustomer->phone ?? null,
                'type' => 'customer',
                'status' => 'visible',
                'created' => Carbon::now(),
            ]);
            $laravelCustomer->amelia_user_id = $ameliaUser->id;
            $laravelCustomer->save();

            return $ameliaUser;
        } catch (\Throwable) {
            return null;
        }
    }
}
