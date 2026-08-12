<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentEvent extends Model
{
    protected $fillable = [

        'event_id',

        'event_type',

        'transaction_id',

        'organization_id',

        'plan_id',

        'amount',

        'currency',

        'status',

        'processed_at',

        'payload',

    ];


    protected $casts = [

        'payload' => 'array',

        'processed_at' => 'datetime',

        'amount' => 'decimal:2',

    ];
}
