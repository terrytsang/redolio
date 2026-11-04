<?php

namespace App\Mail;

use App\Models\AnalysisRun;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sent once, only to someone who asked to be told when their scan finished. */
class AnalysisReadyMail extends Mailable
{
    use Queueable;

    public function __construct(public readonly AnalysisRun $run) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your REDO is ready');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.analysis-ready', with: [
            'host' => parse_url($this->run->normalized_url, PHP_URL_HOST) ?: $this->run->normalized_url,
            'url' => route('analyses.reveal', $this->run->public_id),
        ]);
    }
}
