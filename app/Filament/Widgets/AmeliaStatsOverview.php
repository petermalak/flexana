<?php

namespace App\Filament\Widgets;

use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Infrastructure\Persistence\Eloquent\CustomerModel;
use App\Infrastructure\Persistence\Eloquent\PaymentModel;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AmeliaStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $bookingsTotal = BookingModel::query()->count();
        $bookingsToday = BookingModel::query()
            ->whereBetween('booked_at', [$todayStart, $todayEnd])
            ->count();

        $appointmentsUpcoming = AppointmentModel::query()
            ->where('booking_start', '>=', now())
            ->where('status', 'approved')
            ->count();

        $customersTotal = CustomerModel::query()->count();

        $revenueTotal = (float) PaymentModel::query()->sum('amount');
        $revenueToday = (float) PaymentModel::query()
            ->whereBetween('paid_at', [$todayStart, $todayEnd])
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
