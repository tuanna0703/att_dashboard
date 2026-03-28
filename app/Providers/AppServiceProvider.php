<?php

namespace App\Providers;

use App\Models\ReceiptAllocation;
use App\Observers\ReceiptAllocationObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        ReceiptAllocation::observe(ReceiptAllocationObserver::class);
    }
}
