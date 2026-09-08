<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_is_complete_relative_and_idempotent(): void
    {
        CarbonImmutable::setTestNow('2026-09-08 10:00:00');

        try {
            $this->seed(DatabaseSeeder::class);
            $this->seed(DatabaseSeeder::class);

            $user = User::query()
                ->where('email', 'demo@harmonika.local')
                ->firstOrFail();
            $transactions = $user->financialTransactions()->get()->keyBy('description');

            $this->assertSame('Usuário Demo', $user->name);
            $this->assertTrue(Hash::check('Harmonika@123', $user->password));
            $this->assertCount(2, $user->contacts);
            $this->assertCount(5, $transactions);
            $this->assertSame(
                '2026-09-05',
                $transactions['Hospedagem vencida']->due_date->toDateString()
            );
            $this->assertSame(
                '2026-09-10',
                $transactions['Fatura próxima do vencimento']->due_date->toDateString()
            );
            $this->assertSame('paid', $transactions['Projeto já recebido']->status);
            $this->assertNotNull($transactions['Projeto já recebido']->paid_at);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
