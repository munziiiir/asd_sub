<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ReservationRoom extends Pivot
{
    use HasFactory;

    /** @var string */
    protected $table = 'reservation_room';

    /** @var array<int,string> */
    protected $fillable = [
        'hotel_id',
        'reservation_id',
        'room_id',
        'from_date',
        'to_date',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
