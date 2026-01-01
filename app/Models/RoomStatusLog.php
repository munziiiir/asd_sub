<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomStatusLog extends Model
{
    use HasFactory;

    /** @var array<int,string> */
    protected $fillable = [
        'hotel_id',
        'room_id',
        'reservation_id',
        'changed_by_staff_id',
        'assigned_staff_id',
        'context',
        'previous_status',
        'new_status',
        'revert_to_status',
        'revert_at',
        'reverted_at',
        'note',
        'meta',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'revert_at' => 'datetime',
        'reverted_at' => 'datetime',
        'meta' => 'array',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'changed_by_staff_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'assigned_staff_id');
    }
}
