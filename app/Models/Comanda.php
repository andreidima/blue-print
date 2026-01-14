<?php

namespace App\Models;

use App\Enums\StatusComanda;
use App\Enums\StatusPlata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comanda extends Model
{
    use HasFactory;

    protected $table = 'comenzi';

    protected $fillable = [
        'client_id',
        'tip',
        'sursa',
        'status',
        'timp_estimat_livrare',
        'finalizat_la',
        'necesita_tipar_exemplu',
        'frontdesk_user_id',
        'supervizor_user_id',
        'grafician_user_id',
        'executant_user_id',
        'nota_frontdesk',
        'nota_grafician',
        'nota_executant',
        'total',
        'total_platit',
        'status_plata',
    ];

    protected function casts(): array
    {
        return [
            'timp_estimat_livrare' => 'datetime',
            'finalizat_la' => 'datetime',
            'necesita_tipar_exemplu' => 'boolean',
            'total' => 'decimal:2',
            'total_platit' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function produse(): HasMany
    {
        return $this->hasMany(ComandaProdus::class, 'comanda_id');
    }

    public function atasamente(): HasMany
    {
        return $this->hasMany(ComandaAtasament::class, 'comanda_id');
    }

    public function mockupuri(): HasMany
    {
        return $this->hasMany(Mockup::class, 'comanda_id');
    }

    public function plati(): HasMany
    {
        return $this->hasMany(Plata::class, 'comanda_id');
    }

    public function frontdeskUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'frontdesk_user_id');
    }

    public function supervizorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervizor_user_id');
    }

    public function graficianUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'grafician_user_id');
    }

    public function executantUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executant_user_id');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotIn('status', StatusComanda::finalStates())
            ->where('timp_estimat_livrare', '<', now());
    }

    public function scopeDueSoon(Builder $query, int $hours = 24): Builder
    {
        $now = now();
        return $query->whereNotIn('status', StatusComanda::finalStates())
            ->whereBetween('timp_estimat_livrare', [$now, $now->copy()->addHours($hours)]);
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where(function ($query) use ($userId) {
            $query->where('frontdesk_user_id', $userId)
                ->orWhere('supervizor_user_id', $userId)
                ->orWhere('grafician_user_id', $userId)
                ->orWhere('executant_user_id', $userId);
        });
    }

    public function getIsFinalAttribute(): bool
    {
        return in_array($this->status, StatusComanda::finalStates(), true);
    }

    public function getIsOverdueAttribute(): bool
    {
        return (bool) $this->timp_estimat_livrare
            && !$this->is_final
            && $this->timp_estimat_livrare->isPast();
    }

    public function getIsDueSoonAttribute(): bool
    {
        if (!$this->timp_estimat_livrare || $this->is_final) {
            return false;
        }

        $hours = now()->diffInHours($this->timp_estimat_livrare, false);
        return $hours >= 0 && $hours <= 24;
    }

    public function getIsLateAttribute(): bool
    {
        return (bool) $this->timp_estimat_livrare
            && (bool) $this->finalizat_la
            && $this->finalizat_la->greaterThan($this->timp_estimat_livrare);
    }

    public function recalculateTotals(): void
    {
        $total = $this->produse()->sum('total_linie');
        $totalPlatit = $this->plati()->sum('suma');

        if ($totalPlatit <= 0) {
            $statusPlata = StatusPlata::Neplatit->value;
        } elseif ($total > 0 && $totalPlatit < $total) {
            $statusPlata = StatusPlata::Partial->value;
        } else {
            $statusPlata = StatusPlata::Platit->value;
        }

        $this->forceFill([
            'total' => $total,
            'total_platit' => $totalPlatit,
            'status_plata' => $statusPlata,
        ])->save();
    }
}
