<?php

namespace App\Mail;

use App\Models\FinancialTransaction;
use App\Models\TransactionReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransactionReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly FinancialTransaction $financialTransaction,
        public readonly string $reminderType,
        public readonly string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->reminderType === TransactionReminder::TYPE_DUE_SOON
                ? 'Conta próxima do vencimento'
                : 'Conta em atraso',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.transaction-reminder');
    }
}
