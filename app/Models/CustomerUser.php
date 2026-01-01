<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerUser extends Model
{
    use HasFactory;

    /** @var array<int,string> */
    protected $fillable = [
        'user_id',
        'avatar',
        'name',
        'email',
        'phone',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'billing_address_line1',
        'billing_address_line2',
        'billing_city',
        'billing_state',
        'billing_postal_code',
        'billing_country',
        'card_number_hash',
        'card_cvv_hash',
        'card_brand',
        'card_last_four',
        'card_exp_month',
        'card_exp_year',
        'payment_customer_id',
    ];

    protected $hidden = [
        'billing_address_line1',
        'billing_address_line2',
        'billing_city',
        'billing_state',
        'billing_postal_code',
        'billing_country',
        'card_brand',
        'card_last_four',
        'card_exp_month',
        'card_exp_year',
        'payment_customer_id',
        'card_number_hash',
        'card_cvv_hash',
    ];

    protected $casts = [
        'card_exp_month' => 'integer',
        'card_exp_year' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'customer_id');
    }
}
