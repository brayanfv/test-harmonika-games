<?php

namespace App\Jobs;

use App\Mail\PeriodClosingMail;
use App\Models\PeriodClosing;
use App\Services\PeriodClosingCsvGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class ProcessPeriodClosing implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public int $timeout = 900;

    public int $uniqueFor = 1800;

    public function __construct(public readonly int $periodClosingId) {}

    public function uniqueId(): string
    {
        return "period-closing:{$this->periodClosingId}";
    }

    public function handle(PeriodClosingCsvGenerator $csvGenerator): void
    {
        $periodClosing = $this->claimPeriodClosing();

        if ($periodClosing === null) {
            return;
        }

        try {
            $filePath = $csvGenerator->generate($periodClosing);

            PeriodClosing::query()
                ->whereKey($periodClosing)
                ->where('delivery_token', $periodClosing->delivery_token)
                ->update(['file_path' => $filePath]);

            Mail::to($periodClosing->user->email)->send(new PeriodClosingMail(
                $periodClosing,
                $periodClosing->user->name,
                $filePath,
            ));

            PeriodClosing::query()
                ->whereKey($periodClosing)
                ->where('delivery_token', $periodClosing->delivery_token)
                ->whereNull('sent_at')
                ->update([
                    'status' => PeriodClosing::STATUS_SENT,
                    'sent_at' => now(),
                    'processing_at' => null,
                    'delivery_token' => null,
                    'failed_at' => null,
                    'error_message' => null,
                ]);
        } catch (Throwable $exception) {
            PeriodClosing::query()
                ->whereKey($periodClosing)
                ->where('delivery_token', $periodClosing->delivery_token)
                ->whereNull('sent_at')
                ->update([
                    'status' => PeriodClosing::STATUS_PENDING,
                    'processing_at' => null,
                    'delivery_token' => null,
                    'error_message' => $exception->getMessage(),
                ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        PeriodClosing::query()
            ->whereKey($this->periodClosingId)
            ->whereNull('sent_at')
            ->update([
                'status' => PeriodClosing::STATUS_FAILED,
                'dispatched_at' => null,
                'processing_at' => null,
                'delivery_token' => null,
                'failed_at' => now(),
                'error_message' => $exception?->getMessage(),
            ]);
    }

    private function claimPeriodClosing(): ?PeriodClosing
    {
        return DB::transaction(function (): ?PeriodClosing {
            $periodClosing = PeriodClosing::query()
                ->with('user')
                ->lockForUpdate()
                ->find($this->periodClosingId);

            if ($periodClosing === null || $periodClosing->sent_at !== null) {
                return null;
            }

            if (
                $periodClosing->processing_at !== null
                && $periodClosing->processing_at->isAfter(
                    now()->subSeconds(config('period_closings.stale_after'))
                )
            ) {
                return null;
            }

            $periodClosing->update([
                'status' => PeriodClosing::STATUS_PROCESSING,
                'attempts' => $periodClosing->attempts + 1,
                'dispatched_at' => $periodClosing->dispatched_at ?? now(),
                'processing_at' => now(),
                'delivery_token' => (string) Str::uuid(),
                'failed_at' => null,
            ]);

            return $periodClosing->fresh('user');
        });
    }
}
