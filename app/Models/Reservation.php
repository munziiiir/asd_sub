<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    use HasFactory;

    /** @var array<int,string> */
    protected $fillable = [
        'hotel_id',
        'customer_id',
        'incremental_no',
        'code',
        'status',
        'check_in_date',
        'check_out_date',
        'adults',
        'children',
        'nightly_rate',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'nightly_rate' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation) {
            if (! empty($reservation->code)) {
                return;
            }

            $nextNumber = static::nextIncrementalNumber($reservation->hotel_id);
            $reservation->incremental_no = $nextNumber;

            $hotelCode = $reservation->hotel?->code
                ?? $reservation->hotel()->value('code');

            if (! $hotelCode) {
                throw new \RuntimeException('Unable to generate reservation code without hotel code.');
            }

            $reservation->code = sprintf('%s-%04d', $hotelCode, $nextNumber);
        });
    }

    protected static function nextIncrementalNumber(int $hotelId): int
    {
        $max = static::where('hotel_id', $hotelId)->max('incremental_no');

        return (int) ($max ?? 0) + 1;
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustomerUser::class, 'customer_id');
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class)
            ->using(ReservationRoom::class)
            ->withPivot(['hotel_id', 'from_date', 'to_date'])
            ->withTimestamps();
    }

    public function reservationRooms(): HasMany
    {
        return $this->hasMany(ReservationRoom::class);
    }

    public function occupants(): HasMany
    {
        return $this->hasMany(ReservationOccupant::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function checkOuts(): HasMany
    {
        return $this->hasMany(CheckOut::class);
    }

    public function folios(): HasMany
    {
        return $this->hasMany(Folio::class);
    }

    /**
     * Total nightly charge across all assigned rooms.
     */
    public function nightlyRateTotal(): float
    {
        if ($this->nightly_rate !== null) {
            return (float) $this->nightly_rate;
        }

        $this->loadMissing('rooms.roomType');

        return (float) $this->rooms->sum(
            fn (Room $room) => (float) ($room->roomType?->activeRate() ?? 0)
        );
    }

    /**
     * Comma-separated list of room numbers for display.
     */
    public function roomNumberLabel(): string
    {
        $this->loadMissing('rooms');

        $numbers = $this->rooms->pluck('number')->filter()->sort()->values()->all();

        return $numbers ? implode(', ', $numbers) : 'N/A';
    }
}
