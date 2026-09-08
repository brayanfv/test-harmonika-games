<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $today = CarbonImmutable::today(config('app.timezone'));

        $user = User::query()->updateOrCreate([
            'email' => 'demo@harmonika.local',
        ], [
            'name' => 'Usuário Demo',
            'password' => 'Harmonika@123',
        ]);

        $client = $user->contacts()->updateOrCreate([
            'name' => 'Cliente Aurora',
        ], [
            'email' => 'financeiro@cliente-aurora.local',
            'phone' => '(11) 99999-1001',
        ]);

        $supplier = $user->contacts()->updateOrCreate([
            'name' => 'Fornecedor Pixel',
        ], [
            'email' => 'contato@fornecedor-pixel.local',
            'phone' => '(11) 99999-2002',
        ]);

        $transactions = [
            [
                'description' => 'Licença de software',
                'contact_id' => $supplier->id,
                'type' => 'payable',
                'amount' => 420.50,
                'due_date' => $today->addDays(10),
                'status' => 'pending',
                'paid_at' => null,
            ],
            [
                'description' => 'Projeto em andamento',
                'contact_id' => $client->id,
                'type' => 'receivable',
                'amount' => 2500.00,
                'due_date' => $today->addDays(7),
                'status' => 'pending',
                'paid_at' => null,
            ],
            [
                'description' => 'Projeto já recebido',
                'contact_id' => $client->id,
                'type' => 'receivable',
                'amount' => 900.00,
                'due_date' => $today->subDays(5),
                'status' => 'paid',
                'paid_at' => $today->subDay()->setTime(14, 30),
            ],
            [
                'description' => 'Hospedagem vencida',
                'contact_id' => $supplier->id,
                'type' => 'payable',
                'amount' => 180.00,
                'due_date' => $today->subDays(3),
                'status' => 'pending',
                'paid_at' => null,
            ],
            [
                'description' => 'Fatura próxima do vencimento',
                'contact_id' => $client->id,
                'type' => 'receivable',
                'amount' => 750.00,
                'due_date' => $today->addDays(2),
                'status' => 'pending',
                'paid_at' => null,
            ],
        ];

        foreach ($transactions as $transaction) {
            $user->financialTransactions()->updateOrCreate([
                'description' => $transaction['description'],
            ], $transaction);
        }
    }
}
