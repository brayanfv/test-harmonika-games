<?php

namespace App\Services;

use App\Models\FinancialTransaction;
use App\Models\TransactionReminder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class TransactionReminderEligibility
{
    public function eligibleTransactions(
        string $type,
        CarbonInterface $referenceDate
    ): Builder {
        $date = $referenceDate->toImmutable()->startOfDay();
        $query = FinancialTransaction::query()->where('status', 'pending');

        return match ($type) {
            TransactionReminder::TYPE_DUE_SOON => $query
                ->whereDate('due_date', '>=', $date->toDateString())
                ->whereDate(
                    'due_date',
                    '<=',
                    $date->addDays(config('reminders.due_soon_days'))->toDateString()
                ),
            TransactionReminder::TYPE_OVERDUE => $query
                ->whereDate('due_date', '<', $date->toDateString()),
            default => throw new InvalidArgumentException("Unknown reminder type: {$type}"),
        };
    }

    public function isEligible(
        FinancialTransaction $transaction,
        TransactionReminder $reminder,
        ?CarbonInterface $referenceDate = null
    ): bool {
        if (
            $transaction->status !== 'pending'
            || ! $transaction->due_date->isSameDay($reminder->due_date)
        ) {
            return false;
        }

        $date = ($referenceDate ?? now())->toImmutable()->startOfDay();

        return match ($reminder->type) {
            TransactionReminder::TYPE_DUE_SOON => $transaction->due_date->betweenIncluded(
                $date,
                $date->addDays(config('reminders.due_soon_days'))
            ),
            TransactionReminder::TYPE_OVERDUE => $transaction->due_date->isBefore($date),
            default => false,
        };
    }
}
