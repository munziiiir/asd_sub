<?php

namespace App\Events;

use App\Models\CheckOut;
use App\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationCheckedOut
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public CheckOut $checkOut,
    ) {
    }
}
