<?php

namespace App\Providers;

use App\Jobs\ProcessPeriodClosing;
use App\Jobs\SendTransactionReminder;
use App\Models\PeriodClosing;
use App\Models\TransactionReminder;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\UniqueJobSkipped;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(JobQueued::class, function (JobQueued $event): void {
            if ($event->job instanceof SendTransactionReminder) {
                try {
                    TransactionReminder::query()
                        ->whereKey($event->job->reminderId)
                        ->whereNull('sent_at')
                        ->whereNull('cancelled_at')
                        ->update(['dispatched_at' => now()]);
                } catch (Throwable $exception) {
                    Log::error('Could not record queued transaction reminder.', [
                        'reminder_id' => $event->job->reminderId,
                        'queue_connection' => $event->connectionName,
                        'queue' => $event->queue,
                        'exception' => $exception,
                    ]);
                }

                return;
            }

            if (! $event->job instanceof ProcessPeriodClosing) {
                return;
            }

            try {
                PeriodClosing::query()
                    ->whereKey($event->job->periodClosingId)
                    ->whereNull('sent_at')
                    ->update([
                        'status' => PeriodClosing::STATUS_PENDING,
                        'dispatched_at' => now(),
                        'failed_at' => null,
                        'error_message' => null,
                    ]);
            } catch (Throwable $exception) {
                Log::error('Could not record queued period closing.', [
                    'period_closing_id' => $event->job->periodClosingId,
                    'queue_connection' => $event->connectionName,
                    'queue' => $event->queue,
                    'exception' => $exception,
                ]);
            }
        });

        Event::listen(UniqueJobSkipped::class, function (UniqueJobSkipped $event): void {
            if ($event->job instanceof SendTransactionReminder) {
                Log::warning('Transaction reminder dispatch skipped by unique lock.', [
                    'reminder_id' => $event->job->reminderId,
                    'unique_id' => $event->job->uniqueId(),
                ]);

                return;
            }

            if (! $event->job instanceof ProcessPeriodClosing) {
                return;
            }

            Log::warning('Period closing dispatch skipped by unique lock.', [
                'period_closing_id' => $event->job->periodClosingId,
                'unique_id' => $event->job->uniqueId(),
            ]);
        });
    }
}
