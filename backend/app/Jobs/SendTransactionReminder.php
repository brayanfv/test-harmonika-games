<?php

namespace App\Jobs;

use App\Mail\TransactionReminderMail;
use App\Models\TransactionReminder;
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

class SendTransactionReminder implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public int $timeout = 60;

    public int $uniqueFor = 900;

    public function __construct(public readonly int $reminderId) {}

    public function uniqueId(): string
    {
        return "transaction-reminder:{$this->reminderId}";
    }

    public function handle(): void
    {
        $reminder = $this->claimReminder();

        if ($reminder === null) {
            return;
        }

        try {
            Mail::to($reminder->user->email)->send(new TransactionReminderMail(
                $reminder->financialTransaction,
                $reminder->type,
                $reminder->user->name,
            ));

            TransactionReminder::query()
                ->whereKey($reminder)
                ->where('delivery_token', $reminder->delivery_token)
                ->whereNull('sent_at')
                ->update([
                    'sent_at' => now(),
                    'sending_at' => null,
                    'delivery_token' => null,
                ]);
        } catch (Throwable $exception) {
            TransactionReminder::query()
                ->whereKey($reminder)
                ->where('delivery_token', $reminder->delivery_token)
                ->whereNull('sent_at')
                ->update([
                    'sending_at' => null,
                    'delivery_token' => null,
                ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        TransactionReminder::query()
            ->whereKey($this->reminderId)
            ->whereNull('sent_at')
            ->update([
                'dispatched_at' => null,
                'sending_at' => null,
                'delivery_token' => null,
            ]);
    }

    private function claimReminder(): ?TransactionReminder
    {
        return DB::transaction(function (): ?TransactionReminder {
            $reminder = TransactionReminder::query()
                ->with(['financialTransaction.contact', 'user'])
                ->lockForUpdate()
                ->find($this->reminderId);

            if (
                $reminder === null
                || $reminder->sent_at !== null
                || $reminder->cancelled_at !== null
            ) {
                return null;
            }

            if (
                $reminder->financialTransaction === null
                || $reminder->financialTransaction->status !== 'pending'
            ) {
                $reminder->update([
                    'cancelled_at' => now(),
                    'sending_at' => null,
                    'delivery_token' => null,
                ]);

                return null;
            }

            if ($reminder->sending_at !== null) {
                return null;
            }

            $reminder->update([
                'sending_at' => now(),
                'delivery_token' => (string) Str::uuid(),
            ]);

            return $reminder->fresh(['financialTransaction.contact', 'user']);
        });
    }
}
