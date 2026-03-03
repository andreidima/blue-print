<?php

namespace App\Models;

use App\Enums\StatusComanda;
use App\Enums\StatusPlata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comanda extends Model
{
    use HasFactory, SoftDeletes;

    private const SUPERVISOR_ROLE_SLUGS = ['supervizor', 'superadmin'];
    private const ORDER_STAFF_ROLE_SLUGS = [
        'operator-front-office',
        'financiar',
        'grafician',
        'operator-tipografie',
    ];
    private const FRONTDESK_NOTE_ROLE_SLUGS = ['operator-front-office', 'financiar'];
    private const GRAFICIAN_NOTE_ROLE_SLUGS = ['grafician'];
    private const EXECUTANT_NOTE_ROLE_SLUGS = ['operator-tipografie'];
    private const NECESAR_PRICE_ROLE_SLUGS = ['operator-front-office', 'financiar'];
    private const PLATI_CREATE_ROLE_SLUGS = [
        'operator-front-office',
        'financiar',
        'grafician',
        'operator-tipografie',
    ];
    private const PLATI_EDIT_ROLE_SLUGS = ['operator-front-office', 'financiar'];
    private const FACTURA_VIEW_PERMISSIONS = ['facturi.view', 'facturi.write'];
    private const FACTURA_WRITE_PERMISSION = 'facturi.write';

    protected $table = 'comenzi';

    protected $fillable = [
        'client_id',
        'woocommerce_order_id',
        'tip',
        'sursa',
        'status',
        'data_solicitarii',
        'valabilitate_oferta',
        'timp_estimat_livrare',
        'finalizat_la',
        'necesita_tipar_exemplu',
        'necesita_mockup',
        'adresa_facturare',
        'adresa_livrare',
        'awb',
        'livrator',
        'afiseaza_detalii',
        'frontdesk_user_id',
        'supervizor_user_id',
        'grafician_user_id',
        'executant_user_id',
        'total',
        'total_platit',
        'status_plata',
    ];

    protected function casts(): array
    {
        return [
            'data_solicitarii' => 'date',
            'valabilitate_oferta' => 'date',
            'timp_estimat_livrare' => 'datetime',
            'finalizat_la' => 'datetime',
            'necesita_tipar_exemplu' => 'boolean',
            'necesita_mockup' => 'boolean',
            'afiseaza_detalii' => 'boolean',
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

    public function produsConsumuri(): HasManyThrough
    {
        return $this->hasManyThrough(
            ComandaProdusConsum::class,
            ComandaProdus::class,
            'comanda_id',
            'comanda_produs_id',
            'id',
            'id'
        );
    }

    public function note(): HasMany
    {
        return $this->hasMany(ComandaNota::class, 'comanda_id')->latest();
    }

    public function solicitari(): HasMany
    {
        return $this->hasMany(ComandaSolicitare::class, 'comanda_id')->latest();
    }

    public function atasamente(): HasMany
    {
        return $this->hasMany(ComandaAtasament::class, 'comanda_id');
    }

    public function mockupuri(): HasMany
    {
        return $this->hasMany(Mockup::class, 'comanda_id');
    }

    public function facturi(): HasMany
    {
        return $this->hasMany(ComandaFactura::class, 'comanda_id');
    }

    public function facturaEmails(): HasMany
    {
        return $this->hasMany(ComandaFacturaEmail::class, 'comanda_id');
    }

    public function ofertaEmails(): HasMany
    {
        return $this->hasMany(ComandaOfertaEmail::class, 'comanda_id');
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(ComandaEmailLog::class, 'comanda_id');
    }

    public function smsMessages(): HasMany
    {
        return $this->hasMany(SmsMessage::class, 'comanda_id');
    }

    public function gdprConsents(): HasMany
    {
        return $this->hasMany(ComandaGdprConsent::class, 'comanda_id')->latest('signed_at');
    }

    public function plati(): HasMany
    {
        return $this->hasMany(Plata::class, 'comanda_id');
    }

    public function produsHistories(): HasMany
    {
        return $this->hasMany(ComandaProdusHistory::class, 'comanda_id')->latest();
    }

    public function etapaHistories(): HasMany
    {
        return $this->hasMany(ComandaEtapaHistory::class, 'comanda_id')->latest();
    }

    public function etapaAssignments(): HasMany
    {
        return $this->hasMany(ComandaEtapaUser::class, 'comanda_id');
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

    public function canEditAssignments(?User $user): bool
    {
        return $this->isSupervisor($user)
            || $this->hasAnyRole($user, self::ORDER_STAFF_ROLE_SLUGS);
    }

    public function canEditNotaFrontdesk(?User $user): bool
    {
        return $this->isSupervisor($user)
            || $this->hasAnyRole($user, self::FRONTDESK_NOTE_ROLE_SLUGS);
    }

    public function canEditNotaGrafician(?User $user): bool
    {
        return $this->isSupervisor($user)
            || $this->hasAnyRole($user, self::GRAFICIAN_NOTE_ROLE_SLUGS);
    }

    public function canEditNotaExecutant(?User $user): bool
    {
        return $this->isSupervisor($user)
            || $this->hasAnyRole($user, self::EXECUTANT_NOTE_ROLE_SLUGS);
    }

    public function canManageSolicitari(?User $user): bool
    {
        return $this->isSupervisor($user)
            || $this->hasAnyRole($user, self::ORDER_STAFF_ROLE_SLUGS);
    }

    public function canViewNecesarPrices(?User $user): bool
    {
        return $this->isSupervisor($user)
            || $this->hasAnyRole($user, self::NECESAR_PRICE_ROLE_SLUGS);
    }

    public function canEditNecesar(?User $user): bool
    {
        return $this->canViewNecesarPrices($user);
    }

    public function canAccessOfertaPrices(?User $user): bool
    {
        return $this->canViewNecesarPrices($user);
    }

    public function canManageOrderFiles(?User $user): bool
    {
        return $this->isSupervisor($user)
            || $this->hasAnyRole($user, self::ORDER_STAFF_ROLE_SLUGS);
    }

    public function canCreatePlati(?User $user): bool
    {
        return $this->isSupervisor($user)
            || $this->hasAnyRole($user, self::PLATI_CREATE_ROLE_SLUGS);
    }

    public function canEditExistingPlati(?User $user): bool
    {
        return $this->isSupervisor($user)
            || $this->hasAnyRole($user, self::PLATI_EDIT_ROLE_SLUGS);
    }

    public function canManageFacturi(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasPermission(self::FACTURA_WRITE_PERMISSION);
    }

    public function canViewFacturi(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasAnyPermission(self::FACTURA_VIEW_PERMISSIONS);
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
        return $query->whereHas('etapaAssignments', function ($query) use ($userId) {
            $query->where('user_id', $userId);
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

    private function isSupervisor(?User $user): bool
    {
        return $this->hasAnyRole($user, self::SUPERVISOR_ROLE_SLUGS);
    }

    private function hasAnyRole(?User $user, array $roleSlugs): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasAnyRole($roleSlugs);
    }

    private function hasEtapaAssignment(User $user, string $slug): bool
    {
        return $this->etapaAssignments()
            ->where('user_id', $user->id)
            ->whereHas('etapa', fn ($query) => $query->where('slug', $slug))
            ->exists();
    }
}
