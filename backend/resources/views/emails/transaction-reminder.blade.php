@php
    $isDueSoon = $reminderType === \App\Models\TransactionReminder::TYPE_DUE_SOON;
    $typeLabel = $financialTransaction->type === 'receivable' ? 'a receber' : 'a pagar';
@endphp
<h1>{{ $isDueSoon ? 'Conta próxima do vencimento' : 'Conta em atraso' }}</h1>

<p>Olá, {{ $recipientName }}!</p>

<p>
    A sua conta {{ $typeLabel }} <strong>{{ $financialTransaction->description }}</strong>
    {{ $isDueSoon ? 'vence em' : 'venceu em' }}
    <strong>{{ $financialTransaction->due_date->format('d/m/Y') }}</strong>.
</p>

<p><strong>Valor:</strong> R$ {{ number_format((float) $financialTransaction->amount, 2, ',', '.') }}</p>

@if ($financialTransaction->contact !== null)
    <p><strong>Contato:</strong> {{ $financialTransaction->contact->name }}</p>
@endif

<p>Acesse o sistema para consultar os detalhes.</p>
