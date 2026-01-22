<?php

namespace App\Filament\Widgets;

use App\Infrastructure\Persistence\Eloquent\AmeliaAppointmentModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaCustomerBookingModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaPaymentModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaUserModel;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AmeliaStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $bookingsTotal = AmeliaCustomerBookingModel::query()->count();
        $bookingsToday = AmeliaCustomerBookingModel::query()
            ->whereBetween('created', [$todayStart, $todayEnd])
            ->count();

        $appointmentsUpcoming = AmeliaAppointmentModel::query()
            ->where('bookingStart', '>=', now())
            ->count();

        $customersTotal = AmeliaUserModel::query()
            ->where('type', 'customer')
            ->count();

        // Payments table exists, but may be empty depending on gateway usage.
        $revenueTotal = (float) AmeliaPaymentModel::query()->sum('amount');
        $revenueToday = (float) AmeliaPaymentModel::query()
            ->whereBetween('dateTime', [$todayStart, $todayEnd])
            ->sum('amount');

        return [
            Stat::make('Total Bookings', number_format($bookingsTotal)),
            Stat::make('Bookings Today', number_format($bookingsToday)),
            Stat::make('Upcoming Appointments', number_format($appointmentsUpcoming)),
            Stat::make('Total Customers', number_format($customersTotal)),
            Stat::make('Revenue Today', '$' . number_format($revenueToday, 2)),
            Stat::make('Revenue Total', '$' . number_format($revenueTotal, 2)),
        ];
    }
}

