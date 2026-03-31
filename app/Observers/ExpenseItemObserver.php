<?php

namespace App\Observers;

use App\Models\ExpenseItem;

class ExpenseItemObserver
{
    public function created(ExpenseItem $item): void
    {
        $this->syncExpenseTotal($item);
    }

    public function updated(ExpenseItem $item): void
    {
        $this->syncExpenseTotal($item);
    }

    public function deleted(ExpenseItem $item): void
    {
        $this->syncExpenseTotal($item);
    }

    private function syncExpenseTotal(ExpenseItem $item): void
    {
        $expense = $item->expense;
        if (! $expense) {
            return;
        }

        $total = (float) $expense->items()->sum('amount');

        $expense->timestamps = false;
        $expense->total_amount = $total;
        $expense->save();
        $expense->timestamps = true;
    }
}
