<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashflowSnapshot extends Model
{
    protected $fillable = [
        'snapshot_date',
        'total_ar',
        'total_overdue',
        'due_this_month',
        'forecast_30_days',
        'overdue_count',
        'due_soon_count',
    ];

    protected $casts = [
        'snapshot_date'    => 'date',
        'total_ar'         => 'decimal:2',
        'total_overdue'    => 'decimal:2',
        'due_this_month'   => 'decimal:2',
        'forecast_30_days' => 'decimal:2',
    ];
}
