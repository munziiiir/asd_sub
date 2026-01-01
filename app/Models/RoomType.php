<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    use HasFactory;

    /** @var array<int,string> */
    protected $fillable = [
        'hotel_id',
        'name',
        'max_adults',
        'max_children',
        'base_occupancy',
        'price_off_peak',
        'price_peak',
        'active_rate',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'price_off_peak' => 'decimal:2',
        'price_peak' => 'decimal:2',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function activeRate(): float
    {
        $rate = $this->active_rate === 'peak'
            ? $this->price_peak
            : $this->price_off_peak;

        return (float) $rate;
    }
}
