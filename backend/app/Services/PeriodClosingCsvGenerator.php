<?php

namespace App\Services;

use App\Models\FinancialTransaction;
use App\Models\PeriodClosing;
use App\Support\CsvFormulaSanitizer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PeriodClosingCsvGenerator
{
    public function generate(PeriodClosing $periodClosing): string
    {
        $startDate = $periodClosing->start_date->toImmutable()->startOfDay();
        $endDate = $periodClosing->end_date->toImmutable()->endOfDay();
        $summary = $this->calculateSummary($periodClosing, $startDate, $endDate);
        $filePath = sprintf(
            'period-closings/fechamento-%d-%s-a-%s.csv',
            $periodClosing->id,
            $startDate->toDateString(),
            $endDate->toDateString()
        );

        Storage::disk('local')->makeDirectory('period-closings');

        $stream = fopen(Storage::disk('local')->path($filePath), 'wb');

        if ($stream === false) {
            throw new RuntimeException('Could not create the period closing CSV file.');
        }

        try {
            fwrite($stream, "\xEF\xBB\xBF");
            $this->writeRow($stream, ['Resumo financeiro do período']);
            $this->writeRow($stream, ['Data inicial', $startDate->format('d/m/Y')]);
            $this->writeRow($stream, ['Data final', $endDate->format('d/m/Y')]);
            $this->writeRow($stream, ['A pagar', $this->formatAmount($summary['payable'])]);
            $this->writeRow($stream, ['A receber', $this->formatAmount($summary['receivable'])]);
            $this->writeRow($stream, ['Liquidado', $this->formatAmount($summary['paid'])]);
            $this->writeRow($stream, ['Vencido', $this->formatAmount($summary['overdue'])]);
            $this->writeRow($stream, []);
            $this->writeRow($stream, [
                'ID',
                'Tipo',
                'Descrição',
                'Contato',
                'E-mail do contato',
                'Valor',
                'Vencimento',
                'Status',
                'Liquidado em',
            ]);

            $this->periodTransactions($periodClosing, $startDate, $endDate)
                ->with('contact:id,name,email')
                ->orderBy('id')
                ->chunkById(
                    config('period_closings.chunk_size'),
                    function ($transactions) use ($stream): void {
                        foreach ($transactions as $transaction) {
                            $this->writeRow($stream, [
                                $transaction->id,
                                $transaction->type === 'receivable' ? 'A receber' : 'A pagar',
                                $transaction->description,
                                $transaction->contact?->name ?? '',
                                $transaction->contact?->email ?? '',
                                $this->formatAmount($transaction->amount),
                                $transaction->due_date->format('d/m/Y'),
                                $this->statusLabel($transaction),
                                $transaction->paid_at?->format('d/m/Y H:i') ?? '',
                            ]);
                        }
                    }
                );
        } finally {
            fclose($stream);
        }

        return $filePath;
    }

    private function calculateSummary(
        PeriodClosing $periodClosing,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate
    ): array {
        $openTransactions = FinancialTransaction::query()
            ->where('user_id', $periodClosing->user_id)
            ->where('status', 'pending')
            ->whereBetween('due_date', [$startDate->toDateString(), $endDate->toDateString()]);

        $paidTransactions = FinancialTransaction::query()
            ->where('user_id', $periodClosing->user_id)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate]);

        return [
            'payable' => (string) (clone $openTransactions)->where('type', 'payable')->sum('amount'),
            'receivable' => (string) (clone $openTransactions)->where('type', 'receivable')->sum('amount'),
            'paid' => (string) $paidTransactions->sum('amount'),
            'overdue' => (string) (clone $openTransactions)
                ->whereDate('due_date', '<', now()->toDateString())
                ->sum('amount'),
        ];
    }

    private function periodTransactions(
        PeriodClosing $periodClosing,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate
    ): Builder {
        return FinancialTransaction::query()
            ->where('user_id', $periodClosing->user_id)
            ->where(function (Builder $query) use ($startDate, $endDate): void {
                $query->whereBetween('due_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhere(function (Builder $query) use ($startDate, $endDate): void {
                        $query->where('status', 'paid')
                            ->whereBetween('paid_at', [$startDate, $endDate]);
                    });
            });
    }

    private function statusLabel(FinancialTransaction $transaction): string
    {
        if ($transaction->status === 'paid') {
            return 'Pago';
        }

        return $transaction->due_date->isBefore(now()->startOfDay())
            ? 'Em atraso'
            : 'Em aberto';
    }

    private function formatAmount(string|int|float $amount): string
    {
        return number_format((float) $amount, 2, ',', '.');
    }

    private function writeRow($stream, array $values): void
    {
        $safeValues = array_map(
            fn (mixed $value): mixed => is_string($value)
                ? CsvFormulaSanitizer::sanitize($value)
                : $value,
            $values
        );

        fputcsv($stream, $safeValues, ';', '"', '');
    }
}
