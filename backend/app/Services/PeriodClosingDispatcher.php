<?php

namespace App\Services;

use App\Jobs\ProcessPeriodClosing;
use App\Models\PeriodClosing;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

class PeriodClosingDispatcher
{
    public function __construct(
        private readonly Cache $cache,
    ) {}

    public function dispatchIfAvailable(
        PeriodClosing $periodClosing,
        bool $retryFailed = false,
        bool $markDispatchFailure = true
    ): bool {
        $availableStatuses = [PeriodClosing::STATUS_PENDING];

        if ($retryFailed) {
            $availableStatuses[] = PeriodClosing::STATUS_FAILED;
        }

        $isAvailable = PeriodClosing::query()
            ->whereKey($periodClosing)
            ->whereIn('status', $availableStatuses)
            ->whereNull('dispatched_at')
            ->exists();

        if (! $isAvailable) {
            return false;
        }

        try {
            $pendingDispatch = ProcessPeriodClosing::dispatch($periodClosing->id)
                ->onQueue(config('period_closings.queue'));
            $job = $pendingDispatch->getJob();

            unset($pendingDispatch);

            return $periodClosing->fresh()->dispatched_at !== null;
        } catch (Throwable $exception) {
            if (isset($job)) {
                (new UniqueLock($this->cache))->release($job);
            }

            PeriodClosing::query()
                ->whereKey($periodClosing)
                ->whereNull('sent_at')
                ->update([
                    'status' => $markDispatchFailure
                        ? PeriodClosing::STATUS_FAILED
                        : PeriodClosing::STATUS_PENDING,
                    'dispatched_at' => null,
                    'failed_at' => $markDispatchFailure ? now() : null,
                    'error_message' => $exception->getMessage(),
                ]);

            Log::error('Could not queue period closing.', [
                'period_closing_id' => $periodClosing->id,
                'exception' => $exception,
            ]);

            return false;
        }
    }

    public function releaseStaleProcessing(?PeriodClosing $periodClosing = null): int
    {
        return PeriodClosing::query()
            ->when(
                $periodClosing !== null,
                fn (Builder $query) => $query->whereKey($periodClosing)
            )
            ->where('status', '!=', PeriodClosing::STATUS_SENT)
            ->whereNotNull('dispatched_at')
            ->where(function (Builder $query): void {
                $staleAt = now()->subSeconds(config('period_closings.stale_after'));

                $query->where(function (Builder $query) use ($staleAt): void {
                    $query->where('status', PeriodClosing::STATUS_PENDING)
                        ->where('dispatched_at', '<=', $staleAt);
                })->orWhere(function (Builder $query) use ($staleAt): void {
                    $query->where('status', PeriodClosing::STATUS_PROCESSING)
                        ->where('processing_at', '<=', $staleAt);
                });
            })
            ->update([
                'status' => PeriodClosing::STATUS_PENDING,
                'dispatched_at' => null,
                'processing_at' => null,
                'delivery_token' => null,
            ]);
    }

    public function reconcile(): int
    {
        $this->releaseStaleProcessing();

        $dispatched = 0;

        PeriodClosing::query()
            ->where('status', PeriodClosing::STATUS_PENDING)
            ->whereNull('dispatched_at')
            ->orderBy('id')
            ->chunkById(
                config('period_closings.chunk_size'),
                function ($periodClosings) use (&$dispatched): void {
                    foreach ($periodClosings as $periodClosing) {
                        if ($this->dispatchIfAvailable(
                            $periodClosing,
                            markDispatchFailure: false
                        )) {
                            $dispatched++;
                        }
                    }
                }
            );

        return $dispatched;
    }
}
