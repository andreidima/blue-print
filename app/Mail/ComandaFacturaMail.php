<?php

namespace App\Mail;

use App\Models\Comanda;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ComandaFacturaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Comanda $comanda,
        public string $subjectLine,
        public string $body,
        public Collection $facturi,
    ) {}

    public function build(): self
    {
        $email = $this->subject($this->subjectLine)
            ->view('emails.comenzi.factura')
            ->with([
                'comanda' => $this->comanda,
                'body' => $this->body,
            ]);

        foreach ($this->facturi as $factura) {
            if (!$factura->path || !Storage::disk('public')->exists($factura->path)) {
                continue;
            }

            $email->attachFromStorageDisk('public', $factura->path, $factura->original_name ?? null);
        }

        return $email;
    }
}
