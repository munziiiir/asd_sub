<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Support\ReservationFolioService;
use Illuminate\Console\Command;

class MarkNoShowReservations extends Command
{
    protected $signature = 'reservations:mark-noshow';

    protected $description = 'Automatically mark reservations as NoShow when not checked in by the end of arrival date.';

    public function handle(): int
    {
        $today = now()->toDateString();
        $service = app(ReservationFolioService::class);

        Reservation::query()
            ->whereIn('status', ['Pending', 'Confirmed'])
            ->whereDate('check_in_date', '<', $today)
            ->whereDoesntHave('checkIns')
            ->chunkById(100, function ($reservations) use ($service) {
                foreach ($reservations as $reservation) {
                    $service->applyCancellationPolicy($reservation, 'system-noshow');
                }
            });

        return self::SUCCESS;
    }
}

