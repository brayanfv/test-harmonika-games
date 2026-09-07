<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePeriodClosingRequest;
use App\Jobs\ProcessPeriodClosing;
use App\Models\PeriodClosing;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PeriodClosingController extends Controller
{
    public function __construct(
        private readonly Cache $cache,
    ) {}

    public function store(StorePeriodClosingRequest $request): JsonResponse
    {
        $period = $request->validated();
        $existingClosing = $request->user()
            ->periodClosings()
            ->whereDate('start_date', $period['start_date'])
            ->whereDate('end_date', $period['end_date']);

        $periodClosing = $existingClosing->first();

        if ($periodClosing === null) {
            try {
                $periodClosing = $request->user()->periodClosings()->create([
                    ...$period,
                    'status' => PeriodClosing::STATUS_PENDING,
                ]);
            } catch (UniqueConstraintViolationException) {
                $periodClosing = $existingClosing->firstOrFail();
            }
        }

        $this->releaseStaleProcessing($periodClosing);
        $this->dispatchIfAvailable($periodClosing, retryFailed: true);
        $periodClosing->refresh();

        return response()->json([
            'message' => $this->responseMessage($periodClosing),
            'period_closing' => $periodClosing,
        ], 202);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $periodClosing = $request->user()
            ->periodClosings()
            ->findOrFail($id);

        $this->releaseStaleProcessing($periodClosing);
        $this->dispatchIfAvailable($periodClosing);
        $periodClosing->refresh();

        return response()->json($periodClosing);
    }

    private function dispatchIfAvailable(
        PeriodClosing $periodClosing,
        bool $retryFailed = false
    ): void {
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
            return;
        }

        try {
            $pendingDispatch = ProcessPeriodClosing::dispatch($periodClosing->id)
                ->onQueue(config('period_closings.queue'));
            $job = $pendingDispatch->getJob();

            unset($pendingDispatch);
        } catch (Throwable $exception) {
            if (isset($job)) {
                (new UniqueLock($this->cache))->release($job);
            }

            PeriodClosing::query()
                ->whereKey($periodClosing)
                ->whereNull('sent_at')
                ->update([
                    'status' => PeriodClosing::STATUS_FAILED,
                    'dispatched_at' => null,
                    'failed_at' => now(),
                    'error_message' => $exception->getMessage(),
                ]);

            Log::error('Could not queue period closing.', [
                'period_closing_id' => $periodClosing->id,
                'exception' => $exception,
            ]);

            return;
        }
    }

    private function releaseStaleProcessing(PeriodClosing $periodClosing): void
    {
        PeriodClosing::query()
            ->whereKey($periodClosing)
            ->where('status', '!=', PeriodClosing::STATUS_SENT)
            ->whereNotNull('dispatched_at')
            ->where(function ($query): void {
                $staleAt = now()->subSeconds(config('period_closings.stale_after'));

                $query->where(function ($query) use ($staleAt): void {
                    $query->where('status', PeriodClosing::STATUS_PENDING)
                        ->where('dispatched_at', '<=', $staleAt);
                })->orWhere(function ($query) use ($staleAt): void {
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

    private function responseMessage(PeriodClosing $periodClosing): string
    {
        return match ($periodClosing->status) {
            PeriodClosing::STATUS_SENT => 'Este fechamento já foi enviado por e-mail.',
            PeriodClosing::STATUS_FAILED => 'Não foi possível colocar o fechamento na fila.',
            default => 'Fechamento aceito para processamento.',
        };
    }
}
