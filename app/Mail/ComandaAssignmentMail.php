<?php

namespace App\Mail;

use App\Models\Comanda;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ComandaAssignmentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Comanda $comanda,
        public User $recipient,
        public array $stages,
        public ?User $assignedBy = null,
    ) {}

    public function build(): self
    {
        $this->comanda->loadMissing('client');

        return $this->subject('Asignare noua pe comanda #' . $this->comanda->id)
            ->view('emails.comenzi.asignare')
            ->with([
                'comanda' => $this->comanda,
                'recipient' => $this->recipient,
                'stages' => collect($this->stages)->filter()->unique()->values()->all(),
                'assignedBy' => $this->assignedBy,
                'orderUrl' => route('comenzi.show', $this->comanda),
                'appName' => (string) config('app.name'),
            ]);
    }
}
