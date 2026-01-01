<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Charge extends Model
{
    use HasFactory;

    /** @var array<int,string> */
    protected $fillable = [
        'folio_id',
        'post_date',
        'description',
        'qty',
        'unit_price',
        'tax_amount',
        'total_amount',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'post_date' => 'date',
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }
}
