<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    use HasFactory;

    /** @var array<int,string> */
    protected $fillable = [
        'reservation_id',
        'room_id',
        'handled_by',
        'checked_in_at',
        'identity_verified',
        'identity_document_type',
        'identity_document_number',
        'identity_notes',
        'remarks',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'checked_in_at' => 'datetime',
        'identity_verified' => 'boolean',
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
