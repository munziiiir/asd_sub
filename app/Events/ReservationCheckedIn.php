<?php

namespace App\Events;

use App\Models\CheckIn;
use App\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationCheckedIn
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public CheckIn $checkIn,
    ) {
    }
}
