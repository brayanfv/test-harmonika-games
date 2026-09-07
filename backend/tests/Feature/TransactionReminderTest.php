<?php

namespace Tests\Feature;

use App\Jobs\SendTransactionReminder;
use App\Mail\TransactionReminderMail;
use App\Models\TransactionReminder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TransactionReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_queues_due_soon_and_overdue_pending_transactions_only_once(): void
    {
        Queue::fake();

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

        $job = new SendTransactionReminder($reminder->id);
        $job->handle();
        $job->handle();

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

        (new SendTransactionReminder($reminder->id))->handle();

        Mail::assertNothingSent();
        $this->assertNotNull($reminder->fresh()->cancelled_at);
    }

    public function test_reminder_job_defines_retry_backoff(): void
    {
        $job = new SendTransactionReminder(1);

        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300, 900], $job->backoff);
    }
}
