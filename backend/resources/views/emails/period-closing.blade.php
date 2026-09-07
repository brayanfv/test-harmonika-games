<h1>Fechamento financeiro</h1>

<p>Olá, {{ $recipientName }}!</p>

<p>
    O fechamento do período de
    <strong>{{ $periodClosing->start_date->format('d/m/Y') }}</strong> a
    <strong>{{ $periodClosing->end_date->format('d/m/Y') }}</strong>
    foi concluído.
</p>

<p>O arquivo CSV com o resumo financeiro e os lançamentos do período está anexado.</p>
