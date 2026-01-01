<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckOut extends Model
{
    use HasFactory;

    /** @var array<int,string> */
    protected $fillable = [
        'reservation_id',
        'room_id',
        'handled_by',
        'checked_out_at',
        'room_charges_total',
        'extras_breakdown',
        'extras_total',
        'grand_total',
        'final_payment_method',
        'final_payment_reference',
        'final_payment_status',
        'settled_at',
        'notes',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'checked_out_at' => 'datetime',
        'room_charges_total' => 'decimal:2',
        'extras_breakdown' => 'array',
        'extras_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'settled_at' => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'handled_by');
    }
}
