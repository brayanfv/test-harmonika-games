<?php

namespace App\Services;

use App\Jobs\SendTransactionReminder;
use App\Models\FinancialTransaction;
use App\Models\TransactionReminder;
use Carbon\CarbonInterface;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class TransactionReminderScanner
{
    public function __construct(
        private readonly TransactionReminderEligibility $eligibility,
        private readonly Cache $cache,
    ) {}

    public function scan(?CarbonInterface $referenceDate = null): array
    {
        $date = ($referenceDate ?? now())->toImmutable()->startOfDay();

        $this->releaseStaleReminders();

        return [
            TransactionReminder::TYPE_DUE_SOON => $this->queueReminders(
                $this->eligibility->eligibleTransactions(
                    TransactionReminder::TYPE_DUE_SOON,
                    $date
                ),
                TransactionReminder::TYPE_DUE_SOON,
                $date
            ),
            TransactionReminder::TYPE_OVERDUE => $this->queueReminders(
                $this->eligibility->eligibleTransactions(
                    TransactionReminder::TYPE_OVERDUE,
                    $date
                ),
                TransactionReminder::TYPE_OVERDUE,
                $date
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

    private function queueReminders(
        Builder $query,
        string $type,
        CarbonInterface $referenceDate
    ): int {
        $queued = 0;

        $query->orderBy('id')->chunkById(
            config('reminders.chunk_size'),
            function (Collection $transactions) use (&$queued, $type, $referenceDate): void {
                foreach ($transactions as $transaction) {
                    if ($this->queueReminder($transaction, $type, $referenceDate)) {
                        $queued++;
                    }
                }
            }
        );

        return $queued;
    }

    private function queueReminder(
        FinancialTransaction $transaction,
        string $type,
        CarbonInterface $referenceDate
    ): bool {
        try {
            $reminder = TransactionReminder::query()->firstOrCreate(
                [
                    'financial_transaction_id' => $transaction->id,
                    'type' => $type,
                    'due_date' => $transaction->due_date->toDateString(),
                ],
                [
                    'user_id' => $transaction->user_id,
                ]
            );
        } catch (UniqueConstraintViolationException $exception) {
            $reminder = TransactionReminder::query()
                ->where('financial_transaction_id', $transaction->id)
                ->where('type', $type)
                ->whereDate('due_date', $transaction->due_date->toDateString())
                ->first();

            if ($reminder === null) {
                throw $exception;
            }
        }

        if (
            $reminder->sent_at !== null
            || $reminder->cancelled_at !== null
            || $reminder->dispatched_at !== null
        ) {
            return false;
        }

        try {
            $pendingDispatch = SendTransactionReminder::dispatch(
                $reminder->id,
                $referenceDate->toDateString()
            )
                ->onQueue(config('reminders.queue'));
            $job = $pendingDispatch->getJob();

            unset($pendingDispatch);

            return $reminder->fresh()->dispatched_at !== null;
        } catch (Throwable $exception) {
            if (isset($job)) {
                (new UniqueLock($this->cache))->release($job);
            }

            Log::error('Could not queue transaction reminder.', [
                'reminder_id' => $reminder->id,
                'exception' => $exception,
            ]);

            return false;
        }
    }
}
