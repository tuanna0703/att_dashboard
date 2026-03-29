<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class UpcomingPaymentsMail extends Mailable
{
    use Queueable, SerializesModels;

    public Collection $schedules;
    public string $reportName;
    public float $totalAmount;
    public int $scheduleCount;

    public function __construct(Collection $schedules, string $reportName)
    {
        $this->schedules      = $schedules;
        $this->reportName     = $reportName;
        $this->scheduleCount  = $schedules->count();
        $this->totalAmount    = (float) $schedules->sum('amount');
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
            markdown: 'emails.upcoming-payments',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
