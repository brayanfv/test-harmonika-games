<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionReminder extends Model
{
    public const TYPE_DUE_SOON = 'due_soon';

    public const TYPE_OVERDUE = 'overdue';

    protected $fillable = [
        'financial_transaction_id',
        'user_id',
        'type',
        'due_date',
        'dispatched_at',
        'sending_at',
        'delivery_token',
        'sent_at',
        'cancelled_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'dispatched_at' => 'datetime',
        'sending_at' => 'datetime',
        'sent_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
