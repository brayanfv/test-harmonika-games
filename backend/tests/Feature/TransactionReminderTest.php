<?php

namespace Tests\Feature;

use App\Jobs\SendTransactionReminder;
use App\Mail\TransactionReminderMail;
use App\Models\TransactionReminder;
use App\Models\User;
use App\Services\TransactionReminderEligibility;
use App\Services\TransactionReminderScanner;
use Carbon\CarbonImmutable;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\UniqueJobSkipped;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TransactionReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_queues_due_soon_and_overdue_pending_transactions_only_once(): void
    {
        Queue::fake();
        $this->emitJobQueuedEventsFromFake();

        $referenceDate = CarbonImmutable::parse('2026-09-07');
        $user = User::factory()->create();

        $dueSoonTransaction = $user->financialTransactions()->create([
            'type' => 'payable',
            'description' => 'Conta próxima do vencimento',
            'amount' => 250.00,
            'due_date' => $referenceDate->addDays(3),
        ]);

        $overdueTransaction = $user->financialTransactions()->create([
            'type' => 'receivable',
            'description' => 'Conta em atraso',
            'amount' => 500.00,
            'due_date' => $referenceDate->subDay(),
        ]);

        $user->financialTransactions()->create([
            'type' => 'payable',
            'description' => 'Conta paga',
            'amount' => 100.00,
            'due_date' => $referenceDate->addDays(2),
            'status' => 'paid',
            'paid_at' => $referenceDate,
        ]);

        $this->artisan('transactions:send-reminders', ['--date' => $referenceDate->toDateString()])
            ->expectsOutput('Avisos enfileirados: 1 próximos do vencimento e 1 em atraso.')
            ->assertSuccessful();

        Queue::assertPushed(SendTransactionReminder::class, 2);

        $this->assertDatabaseHas('transaction_reminders', [
            'financial_transaction_id' => $dueSoonTransaction->id,
            'type' => TransactionReminder::TYPE_DUE_SOON,
        ]);

        $this->assertDatabaseHas('transaction_reminders', [
            'financial_transaction_id' => $overdueTransaction->id,
            'type' => TransactionReminder::TYPE_OVERDUE,
        ]);

        $this->artisan('transactions:send-reminders', ['--date' => $referenceDate->toDateString()])
            ->expectsOutput('Avisos enfileirados: 0 próximos do vencimento e 0 em atraso.')
            ->assertSuccessful();

        Queue::assertPushed(SendTransactionReminder::class, 2);
    }

    public function test_job_sends_a_reminder_once_and_records_delivery(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $transaction = $user->financialTransactions()->create([
            'type' => 'receivable',
            'description' => 'Serviço pendente',
            'amount' => 1500.00,
            'due_date' => '2026-09-10',
        ]);

        $reminder = TransactionReminder::create([
            'financial_transaction_id' => $transaction->id,
            'user_id' => $user->id,
            'type' => TransactionReminder::TYPE_DUE_SOON,
            'due_date' => $transaction->due_date,
            'dispatched_at' => now(),
        ]);

        $job = new SendTransactionReminder($reminder->id, '2026-09-07');
        $eligibility = app(TransactionReminderEligibility::class);
        $job->handle($eligibility);
        $job->handle($eligibility);

        Mail::assertSent(TransactionReminderMail::class, 1);

        $this->assertNotNull($reminder->fresh()->sent_at);
    }

    public function test_job_cancels_a_reminder_when_the_transaction_is_already_paid(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $transaction = $user->financialTransactions()->create([
            'type' => 'payable',
            'description' => 'Conta liquidada',
            'amount' => 300.00,
            'due_date' => '2026-09-05',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $reminder = TransactionReminder::create([
            'financial_transaction_id' => $transaction->id,
            'user_id' => $user->id,
            'type' => TransactionReminder::TYPE_OVERDUE,
            'due_date' => $transaction->due_date,
            'dispatched_at' => now(),
        ]);

        (new SendTransactionReminder($reminder->id, '2026-09-07'))
            ->handle(app(TransactionReminderEligibility::class));

        Mail::assertNothingSent();
        $this->assertNotNull($reminder->fresh()->cancelled_at);
    }

    public function test_reminder_job_defines_retry_backoff(): void
    {
        $job = new SendTransactionReminder(1, '2026-09-07');

        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300, 900], $job->backoff);
    }

    public function test_unique_lock_skip_does_not_record_a_false_dispatch(): void
    {
        Event::fake([UniqueJobSkipped::class]);
        Queue::fake();
        $this->emitJobQueuedEventsFromFake();

        $referenceDate = CarbonImmutable::parse('2026-09-07');
        $user = User::factory()->create();
        $transaction = $user->financialTransactions()->create([
            'type' => 'payable',
            'description' => 'Conta com lock',
            'amount' => 100.00,
            'due_date' => $referenceDate->addDay(),
        ]);
        $reminder = TransactionReminder::create([
            'financial_transaction_id' => $transaction->id,
            'user_id' => $user->id,
            'type' => TransactionReminder::TYPE_DUE_SOON,
            'due_date' => $transaction->due_date,
        ]);
        $job = new SendTransactionReminder($reminder->id, $referenceDate->toDateString());
        $uniqueLock = new UniqueLock(app(Cache::class));

        $this->assertTrue($uniqueLock->acquire($job));

        try {
            $queued = app(TransactionReminderScanner::class)->scan($referenceDate);

            $this->assertSame(0, $queued[TransactionReminder::TYPE_DUE_SOON]);
            Queue::assertNothingPushed();
            Event::assertDispatched(UniqueJobSkipped::class);
            $this->assertNull($reminder->fresh()->dispatched_at);
        } finally {
            $uniqueLock->release($job);
        }

        $queued = app(TransactionReminderScanner::class)->scan($referenceDate);

        $this->assertSame(1, $queued[TransactionReminder::TYPE_DUE_SOON]);
        Queue::assertPushed(SendTransactionReminder::class, 1);
        $this->assertNotNull($reminder->fresh()->dispatched_at);
    }

    public function test_changed_due_date_creates_a_new_reminder_cycle(): void
    {
        Queue::fake();
        $this->emitJobQueuedEventsFromFake();
        Mail::fake();

        $user = User::factory()->create();
        $transaction = $user->financialTransactions()->create([
            'type' => 'receivable',
            'description' => 'Conta reagendada',
            'amount' => 200.00,
            'due_date' => '2026-09-23',
        ]);

        $scanner = app(TransactionReminderScanner::class);
        $eligibility = app(TransactionReminderEligibility::class);

        $firstScan = $scanner->scan(CarbonImmutable::parse('2026-09-20'));
        $firstJob = Queue::pushed(SendTransactionReminder::class)->first();
        $firstJob->handle($eligibility);
        $firstReminder = TransactionReminder::query()->firstOrFail();

        $transaction->update(['due_date' => '2026-09-30']);

        $secondScan = $scanner->scan(CarbonImmutable::parse('2026-09-27'));
        $secondJob = Queue::pushed(SendTransactionReminder::class)->last();
        $secondJob->handle($eligibility);
        $secondReminder = TransactionReminder::query()->latest('id')->firstOrFail();

        $rerun = $scanner->scan(CarbonImmutable::parse('2026-09-27'));

        $this->assertSame(1, $firstScan[TransactionReminder::TYPE_DUE_SOON]);
        $this->assertSame(1, $secondScan[TransactionReminder::TYPE_DUE_SOON]);
        $this->assertSame(0, $rerun[TransactionReminder::TYPE_DUE_SOON]);
        $this->assertDatabaseCount('transaction_reminders', 2);
        $this->assertNotSame($firstReminder->id, $secondReminder->id);
        $this->assertSame('2026-09-23', $firstReminder->due_date->toDateString());
        $this->assertSame('2026-09-30', $secondReminder->due_date->toDateString());
        $this->assertNotNull($firstReminder->sent_at);
        $this->assertNotNull($secondReminder->sent_at);
        Mail::assertSent(TransactionReminderMail::class, 2);
        Queue::assertPushed(SendTransactionReminder::class, 2);
    }

    public function test_job_cancels_reminder_when_due_date_is_no_longer_eligible(): void
    {
        CarbonImmutable::setTestNow('2026-09-07 10:00:00');
        Mail::fake();

        try {
            $user = User::factory()->create();
            $transaction = $user->financialTransactions()->create([
                'type' => 'payable',
                'description' => 'Conta reagendada para depois',
                'amount' => 300.00,
                'due_date' => '2026-09-08',
            ]);
            $reminder = TransactionReminder::create([
                'financial_transaction_id' => $transaction->id,
                'user_id' => $user->id,
                'type' => TransactionReminder::TYPE_DUE_SOON,
                'due_date' => $transaction->due_date,
                'dispatched_at' => now(),
            ]);

            $transaction->update(['due_date' => '2026-10-01']);

            (new SendTransactionReminder($reminder->id, '2026-09-07'))
                ->handle(app(TransactionReminderEligibility::class));

            Mail::assertNothingSent();
            $this->assertNotNull($reminder->fresh()->cancelled_at);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_business_timezone_is_used_at_the_utc_date_boundary(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-08 02:30:00', 'UTC'));
        Queue::fake();
        $this->emitJobQueuedEventsFromFake();

        try {
            $this->assertSame('America/Sao_Paulo', config('app.timezone'));
            $this->assertSame('2026-09-07', now()->toDateString());

            $user = User::factory()->create();
            $transaction = $user->financialTransactions()->create([
                'type' => 'payable',
                'description' => 'Conta vence hoje no Brasil',
                'amount' => 100.00,
                'due_date' => '2026-09-07',
            ]);

            app(TransactionReminderScanner::class)->scan();

            $this->assertDatabaseHas('transaction_reminders', [
                'financial_transaction_id' => $transaction->id,
                'type' => TransactionReminder::TYPE_DUE_SOON,
            ]);
            $this->assertDatabaseMissing('transaction_reminders', [
                'financial_transaction_id' => $transaction->id,
                'type' => TransactionReminder::TYPE_OVERDUE,
            ]);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_manual_reference_date_is_used_when_the_queued_job_runs(): void
    {
        CarbonImmutable::setTestNow('2026-09-07 10:00:00');
        Queue::fake();
        $this->emitJobQueuedEventsFromFake();
        Mail::fake();

        try {
            $user = User::factory()->create();
            $transaction = $user->financialTransactions()->create([
                'type' => 'receivable',
                'description' => 'Conta elegível apenas na data simulada',
                'amount' => 450.00,
                'due_date' => '2026-09-18',
            ]);

            $this->artisan('transactions:send-reminders', ['--date' => '2026-09-15'])
                ->expectsOutput('Avisos enfileirados: 1 próximos do vencimento e 0 em atraso.')
                ->assertSuccessful();

            $queuedJob = Queue::pushed(SendTransactionReminder::class)->first();

            $this->assertSame('2026-09-15', $queuedJob->referenceDate);

            $queuedJob->handle(app(TransactionReminderEligibility::class));

            Mail::assertSent(TransactionReminderMail::class, 1);
            $this->assertDatabaseHas('transaction_reminders', [
                'financial_transaction_id' => $transaction->id,
                'type' => TransactionReminder::TYPE_DUE_SOON,
                'sent_at' => now(),
                'cancelled_at' => null,
            ]);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    private function emitJobQueuedEventsFromFake(): void
    {
        Queue::afterPushing(function ($job, $data, $queue): void {
            event(new JobQueued(
                config('queue.default'),
                $queue,
                'fake-job-id',
                $job,
                '{}',
                null,
            ));
        });
    }
}
