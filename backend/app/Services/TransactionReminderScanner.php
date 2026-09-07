<?php

namespace App\Services;

use App\Jobs\SendTransactionReminder;
use App\Models\FinancialTransaction;
use App\Models\TransactionReminder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class TransactionReminderScanner
{
    public function scan(?CarbonInterface $referenceDate = null): array
    {
        $date = ($referenceDate ?? now())->toImmutable()->startOfDay();

        $this->releaseStaleReminders();

        return [
            TransactionReminder::TYPE_DUE_SOON => $this->queueReminders(
                FinancialTransaction::query()
                    ->where('status', 'pending')
                    ->whereDate('due_date', '>=', $date->toDateString())
                    ->whereDate(
                        'due_date',
                        '<=',
                        $date->addDays(config('reminders.due_soon_days'))->toDateString()
                    ),
                TransactionReminder::TYPE_DUE_SOON
            ),
            TransactionReminder::TYPE_OVERDUE => $this->queueReminders(
                FinancialTransaction::query()
                    ->where('status', 'pending')
                    ->whereDate('due_date', '<', $date->toDateString()),
                TransactionReminder::TYPE_OVERDUE
            ),
        ];
    }

    private function releaseStaleReminders(): void
    {
        TransactionReminder::query()
            ->whereNull('sent_at')
            ->whereNull('cancelled_at')
            ->whereNotNull('dispatched_at')
            ->where('dispatched_at', '<=', now()->subSeconds(config('reminders.stale_after')))
            ->update([
                'dispatched_at' => null,
                'sending_at' => null,
                'delivery_token' => null,
            ]);
    }

    private function queueReminders(Builder $query, string $type): int
    {
        $queued = 0;

        $query->orderBy('id')->chunkById(
            config('reminders.chunk_size'),
            function (Collection $transactions) use (&$queued, $type): void {
                foreach ($transactions as $transaction) {
                    if ($this->queueReminder($transaction, $type)) {
                        $queued++;
                    }
                }
            }
        );

        return $queued;
    }

    private function queueReminder(FinancialTransaction $transaction, string $type): bool
    {
        try {
            $reminder = TransactionReminder::query()->firstOrCreate(
                [
                    'financial_transaction_id' => $transaction->id,
                    'type' => $type,
                ],
                [
                    'user_id' => $transaction->user_id,
                    'due_date' => $transaction->due_date,
                ]
            );
        } catch (UniqueConstraintViolationException) {
            $reminder = TransactionReminder::query()
                ->where('financial_transaction_id', $transaction->id)
                ->where('type', $type)
                ->firstOrFail();
        }

        $claimed = TransactionReminder::query()
            ->whereKey($reminder)
            ->whereNull('sent_at')
            ->whereNull('cancelled_at')
            ->whereNull('dispatched_at')
            ->update(['dispatched_at' => now()]);

        if ($claimed === 0) {
            return false;
        }

        try {
            SendTransactionReminder::dispatch($reminder->id)
                ->onQueue(config('reminders.queue'));

            return true;
        } catch (Throwable $exception) {
            TransactionReminder::query()
                ->whereKey($reminder)
                ->whereNull('sent_at')
                ->update(['dispatched_at' => null]);

            Log::error('Could not queue transaction reminder.', [
                'reminder_id' => $reminder->id,
                'exception' => $exception,
            ]);

            return false;
        }
    }
}
