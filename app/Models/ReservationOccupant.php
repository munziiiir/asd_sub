<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationOccupant extends Model
{
    use HasFactory;

    /** @var array<int,string> */
    protected $fillable = [
        'reservation_id',
        'full_name',
        'age',
        'type',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
