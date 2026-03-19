<?php

namespace App\Models;

use App\Support\ClientEmailSupport;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'clienti';

    protected $with = ['emails'];

    protected $fillable = [
        'type',
        'nume',
        'adresa',
        'telefon',
        'telefon_secundar',
        'email',
        'cnp',
        'sex',
        'reg_com',
        'cui',
        'iban',
        'banca',
        'reprezentant',
        'reprezentant_functie',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function comenzi(): HasMany
    {
        return $this->hasMany(Comanda::class, 'client_id');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(ClientEmail::class, 'client_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function getNumeCompletAttribute(): string
    {
        return trim((string) ($this->nume ?? ''));
    }

    public function getEmailAddressesAttribute(): array
    {
        $emails = $this->relationLoaded('emails')
            ? $this->emails
            : $this->emails()->get();

        $normalized = ClientEmailSupport::normalize(
            $emails->pluck('email')->all()
        );

        if ($normalized !== []) {
            return $normalized;
        }

        return ClientEmailSupport::normalize([$this->getRawOriginal('email')]);
    }

    public function getPrimaryEmailAttribute(): ?string
    {
        return ClientEmailSupport::first($this->email_addresses);
    }

    public function syncEmailAddresses(array $emails): void
    {
        $emails = ClientEmailSupport::normalize($emails);

        $this->emails()->delete();

        if ($emails !== []) {
            $this->emails()->createMany(
                collect($emails)
                    ->values()
                    ->map(fn ($email, $index) => [
                        'email' => $email,
                        'type' => null,
                        'sort_order' => $index,
                    ])
                    ->all()
            );
        }

        $this->forceFill([
            'email' => ClientEmailSupport::first($emails),
        ])->saveQuietly();

        $this->unsetRelation('emails');
        $this->load('emails');
    }

    public function addEmailAddress(?string $email): void
    {
        $email = ClientEmailSupport::first([$email]);
        if (!$email) {
            return;
        }

        if (in_array($email, $this->email_addresses, true)) {
            return;
        }

        $nextSortOrder = (int) ($this->emails()->max('sort_order') ?? -1) + 1;

        $this->emails()->create([
            'email' => $email,
            'type' => null,
            'sort_order' => $nextSortOrder,
        ]);

        if (!$this->getRawOriginal('email')) {
            $this->forceFill(['email' => $email])->saveQuietly();
        }

        $this->unsetRelation('emails');
        $this->load('emails');
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                return ClientEmailSupport::format($this->email_addresses)
                    ?? (trim((string) $value) ?: null);
            }
        );
    }
}
