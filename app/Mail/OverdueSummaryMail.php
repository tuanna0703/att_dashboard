<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class OverdueSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public Collection $schedules;
    public string $reportName;
    public float $totalOverdue;
    public int $overdueCount;

    public function __construct(Collection $schedules, string $reportName)
    {
        $this->schedules   = $schedules;
        $this->reportName  = $reportName;
        $this->overdueCount = $schedules->count();
        $this->totalOverdue = (float) $schedules->sum('amount');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[ATT] ' . $this->reportName . ' — ' . now()->format('d/m/Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.overdue-summary',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
