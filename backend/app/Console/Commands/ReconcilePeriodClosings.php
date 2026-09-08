<?php

namespace App\Console\Commands;

use App\Services\PeriodClosingDispatcher;
use Illuminate\Console\Command;

class ReconcilePeriodClosings extends Command
{
    protected $signature = 'period-closings:reconcile';

    protected $description = 'Reencaminha fechamentos pendentes ou interrompidos sem job ativo.';

    public function handle(PeriodClosingDispatcher $dispatcher): int
    {
        $dispatched = $dispatcher->reconcile();

        $this->info("Fechamentos reencaminhados: {$dispatched}.");

        return self::SUCCESS;
    }
}
