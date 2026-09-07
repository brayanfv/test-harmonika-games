<?php

namespace App\Mail;

use App\Models\PeriodClosing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class PeriodClosingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly PeriodClosing $periodClosing,
        public readonly string $recipientName,
        public readonly string $filePath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Fechamento financeiro de %s a %s',
                $this->periodClosing->start_date->format('d/m/Y'),
                $this->periodClosing->end_date->format('d/m/Y')
            ),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.period-closing');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath(Storage::disk('local')->path($this->filePath))
                ->as(sprintf(
                    'fechamento-%s-a-%s.csv',
                    $this->periodClosing->start_date->toDateString(),
                    $this->periodClosing->end_date->toDateString()
                ))
                ->withMime('text/csv'),
        ];
    }
}
