<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'reservation_id','amount','payment_method','payment_date','status',
        'reference','gateway_provider','gateway_intent_id','gateway_charge_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
    ];

    public function reservation() { return $this->belongsTo(Reservation::class); }
}

