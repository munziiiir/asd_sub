<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class StaffUser extends Authenticatable
{
    use HasFactory, Notifiable;

    /** @var array<int,string> */
    protected $guarded = [
        'id',
        'role',
        'employment_status',
        'last_login_at',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'last_password_changed_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class, 'handled_by');
    }

    public function checkOuts(): HasMany
    {
        return $this->hasMany(CheckOut::class, 'handled_by');
    }
}
