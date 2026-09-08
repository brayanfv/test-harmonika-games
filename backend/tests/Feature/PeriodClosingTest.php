<?php

namespace Tests\Feature;

use App\Jobs\ProcessPeriodClosing;
use App\Mail\PeriodClosingMail;
use App\Models\PeriodClosing;
use App\Models\User;
use App\Services\PeriodClosingCsvGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\UniqueJobSkipped;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class PeriodClosingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_request_a_period_closing_only_once(): void
    {
        Queue::fake();
        $this->emitJobQueuedEventsFromFake();

        $user = User::factory()->create();
        $payload = [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ];

        $firstResponse = $this->actingAs($user)->postJson('/api/period-closings', $payload);
        $secondResponse = $this->actingAs($user)->postJson('/api/period-closings', $payload);

        $firstResponse
            ->assertAccepted()
            ->assertJsonPath('period_closing.status', PeriodClosing::STATUS_PENDING);
        $secondResponse
            ->assertAccepted()
            ->assertJsonPath(
                'period_closing.id',
                $firstResponse->json('period_closing.id')
            );

        $this->assertDatabaseCount('period_closings', 1);
        Queue::assertPushedOn(config('period_closings.queue'), ProcessPeriodClosing::class);
        Queue::assertPushed(ProcessPeriodClosing::class, 1);
        $this->assertNotNull(PeriodClosing::query()->firstOrFail()->dispatched_at);
    }

    public function test_period_closing_requires_a_valid_date_range(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/period-closings', [
                'start_date' => '2026-09-30',
                'end_date' => '2026-09-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);

        $this->assertDatabaseCount('period_closings', 0);
        Queue::assertNothingPushed();
    }

    public function test_unique_lock_skip_does_not_leave_a_false_dispatched_state(): void
    {
        Event::fake([UniqueJobSkipped::class]);
        Queue::fake();

        $user = User::factory()->create();
        $periodClosing = $user->periodClosings()->create([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);
        $job = new ProcessPeriodClosing($periodClosing->id);
        $uniqueLock = new UniqueLock(app(Cache::class));

        $this->assertTrue($uniqueLock->acquire($job));

        try {
            $this->actingAs($user)
                ->postJson('/api/period-closings', [
                    'start_date' => '2026-09-01',
                    'end_date' => '2026-09-30',
                ])
                ->assertAccepted()
                ->assertJsonPath('period_closing.dispatched_at', null);

            Queue::assertNothingPushed();
            Event::assertDispatched(UniqueJobSkipped::class);
            $this->assertNull($periodClosing->fresh()->dispatched_at);
        } finally {
            $uniqueLock->release($job);
        }

        $this->actingAs($user)
            ->getJson("/api/period-closings/{$periodClosing->id}")
            ->assertOk();

        Queue::assertPushed(ProcessPeriodClosing::class, 1);
        $this->assertNull($periodClosing->fresh()->dispatched_at);
    }

    public function test_dispatched_at_is_recorded_only_after_the_queue_accepts_the_job(): void
    {
        $user = User::factory()->create();
        $periodClosing = $user->periodClosings()->create([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);
        $job = (new ProcessPeriodClosing($periodClosing->id))
            ->onQueue(config('period_closings.queue'));

        $this->assertNull($periodClosing->dispatched_at);

        event(new JobQueued(
            'redis',
            config('period_closings.queue'),
            'job-id',
            $job,
            '{}',
            null,
        ));

        $this->assertNotNull($periodClosing->fresh()->dispatched_at);
    }

    public function test_user_cannot_view_another_users_period_closing(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();
        $periodClosing = $anotherUser->periodClosings()->create([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        $this->actingAs($user)
            ->getJson("/api/period-closings/{$periodClosing->id}")
            ->assertNotFound();
    }

    public function test_guest_cannot_request_or_view_a_period_closing(): void
    {
        $this->postJson('/api/period-closings', [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ])->assertUnauthorized();

        $this->getJson('/api/period-closings/1')->assertUnauthorized();
    }

    public function test_job_generates_the_csv_sends_one_email_and_records_delivery(): void
    {
        CarbonImmutable::setTestNow('2026-09-15 10:00:00');
        Mail::fake();
        Storage::fake('local');

        $user = User::factory()->create();
        $contact = $user->contacts()->create([
            'name' => 'Cliente do período',
            'email' => 'cliente@example.com',
        ]);

        $user->financialTransactions()->create([
            'contact_id' => $contact->id,
            'type' => 'payable',
            'description' => 'Conta vencida',
            'amount' => 100.00,
            'due_date' => '2026-09-10',
        ]);
        $user->financialTransactions()->create([
            'type' => 'receivable',
            'description' => 'Receita em aberto',
            'amount' => 200.00,
            'due_date' => '2026-09-20',
        ]);
        $user->financialTransactions()->create([
            'type' => 'receivable',
            'description' => 'Receita liquidada no período',
            'amount' => 300.00,
            'due_date' => '2026-08-20',
            'status' => 'paid',
            'paid_at' => '2026-09-05 12:00:00',
        ]);
        $user->financialTransactions()->create([
            'type' => 'payable',
            'description' => 'Fora do período',
            'amount' => 900.00,
            'due_date' => '2026-10-05',
        ]);

        $anotherUser = User::factory()->create();
        $anotherUser->financialTransactions()->create([
            'type' => 'payable',
            'description' => 'Lançamento isolado',
            'amount' => 9999.00,
            'due_date' => '2026-09-10',
        ]);

        $periodClosing = $user->periodClosings()->create([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'dispatched_at' => now(),
        ]);

        $job = new ProcessPeriodClosing($periodClosing->id);
        $job->handle(app(PeriodClosingCsvGenerator::class));
        $job->handle(app(PeriodClosingCsvGenerator::class));

        Mail::assertSent(PeriodClosingMail::class, 1);

        $periodClosing->refresh();

        $this->assertSame(PeriodClosing::STATUS_SENT, $periodClosing->status);
        $this->assertNotNull($periodClosing->sent_at);
        $this->assertSame(1, $periodClosing->attempts);
        Storage::disk('local')->assertExists($periodClosing->getRawOriginal('file_path'));

        $csv = Storage::disk('local')->get($periodClosing->getRawOriginal('file_path'));

        $this->assertStringContainsString('Conta vencida', $csv);
        $this->assertStringContainsString('Receita em aberto', $csv);
        $this->assertStringContainsString('Receita liquidada no período', $csv);
        $this->assertStringNotContainsString('Fora do período', $csv);
        $this->assertStringNotContainsString('Lançamento isolado', $csv);
        $this->assertStringContainsString('100,00', $csv);
        $this->assertStringContainsString('200,00', $csv);
        $this->assertStringContainsString('300,00', $csv);
        $this->assertStringContainsString('Em atraso', $csv);

        CarbonImmutable::setTestNow();
    }

    public function test_csv_neutralizes_formula_like_text_fields(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $contact = $user->contacts()->create([
            'name' => '+Contato',
            'email' => '-email@example.com',
        ]);

        $user->financialTransactions()->create([
            'contact_id' => $contact->id,
            'type' => 'receivable',
            'description' => '=FORMULA',
            'amount' => 100.00,
            'due_date' => '2026-09-10',
        ]);
        $user->financialTransactions()->create([
            'type' => 'payable',
            'description' => '@COMANDO',
            'amount' => 50.00,
            'due_date' => '2026-09-11',
        ]);

        $periodClosing = $user->periodClosings()->create([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        $filePath = app(PeriodClosingCsvGenerator::class)->generate($periodClosing);
        $csv = Storage::disk('local')->get($filePath);

        $this->assertStringContainsString("'=FORMULA", $csv);
        $this->assertStringContainsString("'+Contato", $csv);
        $this->assertStringContainsString("'-email@example.com", $csv);
        $this->assertStringContainsString("'@COMANDO", $csv);
    }

    public function test_failed_job_records_the_failure_and_can_be_requested_again(): void
    {
        Queue::fake();
        $this->emitJobQueuedEventsFromFake();

        $user = User::factory()->create();
        $periodClosing = $user->periodClosings()->create([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => PeriodClosing::STATUS_PROCESSING,
            'dispatched_at' => now(),
            'processing_at' => now(),
        ]);

        (new ProcessPeriodClosing($periodClosing->id))->failed(new RuntimeException('SMTP unavailable'));

        $periodClosing->refresh();

        $this->assertSame(PeriodClosing::STATUS_FAILED, $periodClosing->status);
        $this->assertSame('SMTP unavailable', $periodClosing->getRawOriginal('error_message'));
        $this->assertSame(PeriodClosing::PUBLIC_ERROR_MESSAGE, $periodClosing->error_message);
        $this->assertNotNull($periodClosing->failed_at);

        $this->actingAs($user)
            ->postJson('/api/period-closings', [
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-30',
            ])
            ->assertAccepted()
            ->assertJsonPath('period_closing.status', PeriodClosing::STATUS_PENDING);

        $this->assertDatabaseCount('period_closings', 1);
        Queue::assertPushed(ProcessPeriodClosing::class, 1);
    }

    public function test_period_closing_api_does_not_expose_internal_error_details(): void
    {
        $user = User::factory()->create();
        $internalError = 'SMTP connection failed at /var/www/vendor/mail.php for host mailpit';
        $periodClosing = $user->periodClosings()->create([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => PeriodClosing::STATUS_FAILED,
            'error_message' => $internalError,
            'failed_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson("/api/period-closings/{$periodClosing->id}")
            ->assertOk()
            ->assertJsonPath('error_message', PeriodClosing::PUBLIC_ERROR_MESSAGE)
            ->assertJsonMissingExact(['error_message' => $internalError]);

        $this->assertSame(
            $internalError,
            $periodClosing->fresh()->getRawOriginal('error_message')
        );
    }

    public function test_period_closing_job_defines_retry_and_backoff(): void
    {
        $job = new ProcessPeriodClosing(1);

        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300, 900], $job->backoff);
        $this->assertSame(900, $job->timeout);
    }

    public function test_reconciliation_requeues_pending_and_stale_closings_once(): void
    {
        CarbonImmutable::setTestNow('2026-09-07 12:00:00');
        Queue::fake();
        $this->emitJobQueuedEventsFromFake();

        try {
            $user = User::factory()->create();
            $pendingClosing = $user->periodClosings()->create([
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
            ]);
            $staleClosing = $user->periodClosings()->create([
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-31',
                'status' => PeriodClosing::STATUS_PROCESSING,
                'dispatched_at' => now()->subHour(),
                'processing_at' => now()->subHour(),
                'delivery_token' => 'stale-token',
            ]);
            $activeClosing = $user->periodClosings()->create([
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-30',
                'dispatched_at' => now(),
            ]);
            $user->periodClosings()->create([
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
                'status' => PeriodClosing::STATUS_SENT,
                'sent_at' => now(),
            ]);

            $this->artisan('period-closings:reconcile')
                ->expectsOutput('Fechamentos reencaminhados: 2.')
                ->assertSuccessful();

            Queue::assertPushed(ProcessPeriodClosing::class, 2);
            $this->assertNotNull($pendingClosing->fresh()->dispatched_at);
            $this->assertSame(PeriodClosing::STATUS_PENDING, $staleClosing->fresh()->status);
            $this->assertNotNull($staleClosing->fresh()->dispatched_at);
            $this->assertNull($staleClosing->fresh()->delivery_token);
            $this->assertSame(
                $activeClosing->dispatched_at->toDateTimeString(),
                $activeClosing->fresh()->dispatched_at->toDateTimeString()
            );

            $this->artisan('period-closings:reconcile')
                ->expectsOutput('Fechamentos reencaminhados: 0.')
                ->assertSuccessful();

            Queue::assertPushed(ProcessPeriodClosing::class, 2);
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
