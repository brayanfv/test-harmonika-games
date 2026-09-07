<?php

namespace App\Console\Commands;

use App\Services\TransactionReminderScanner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class DispatchTransactionReminders extends Command
{
    protected $signature = 'transactions:send-reminders {--date= : Data de referência no formato YYYY-MM-DD}';

    protected $description = 'Enfileira avisos de transações próximas do vencimento ou em atraso.';

    public function handle(TransactionReminderScanner $scanner): int
    {
        try {
            $referenceDate = $this->option('date') === null
                ? null
                : CarbonImmutable::createFromFormat('!Y-m-d', $this->option('date'));
        } catch (Throwable) {
            $this->error('A opção --date deve usar o formato YYYY-MM-DD.');

            return self::FAILURE;
        }

        $queued = $scanner->scan($referenceDate);

        $this->info("Avisos enfileirados: {$queued['due_soon']} próximos do vencimento e {$queued['overdue']} em atraso.");

        return self::SUCCESS;
    }
}
