<?php

namespace App\Mail;

use App\Models\Comanda;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ComandaFacturaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Comanda $comanda,
        public string $subjectLine,
        public string $body,
        public array $downloadLinks,
    ) {}

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view('emails.comenzi.factura')
            ->with([
                'comanda' => $this->comanda,
                'bodyHtml' => $this->body,
                'downloadLinks' => $this->downloadLinks,
            ]);
    }
}
