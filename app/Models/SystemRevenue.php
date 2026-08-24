<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemRevenue extends Model
{
    protected $table = 'system_revenue';

    protected $fillable = [
        'wallet_transaction_id',
        'amount_cents',
        'type',
    ];

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }
}
