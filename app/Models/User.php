<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'activ',
        'telefon',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activ' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot(['starts_at', 'ends_at']);
    }

    public function scopeWithoutActiveRoles(Builder $query, array $roleSlugs): Builder
    {
        $slugs = collect($roleSlugs)
            ->filter(fn ($slug) => is_string($slug) && trim($slug) !== '')
            ->map(fn (string $slug) => Role::normalizeIdentifierForChecks($slug))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($slugs === []) {
            return $query;
        }

        $today = now((string) config('app.timezone', 'UTC'))->toDateString();

        return $query->whereDoesntHave('roles', function (Builder $roleQuery) use ($slugs, $today) {
            $roleQuery
                ->whereIn('slug', $slugs)
                ->where(function (Builder $dateQuery) use ($today) {
                    $dateQuery->whereNull('role_user.starts_at')
                        ->orWhereDate('role_user.starts_at', '<=', $today);
                })
                ->where(function (Builder $dateQuery) use ($today) {
                    $dateQuery->whereNull('role_user.ends_at')
                        ->orWhereDate('role_user.ends_at', '>=', $today);
                });
        });
    }

    public function scopeVisibleTo(Builder $query, ?self $viewer): Builder
    {
        if (!$viewer || $viewer->isSuperAdmin()) {
            return $query;
        }

        $hiddenRoleSlugs = ['superadmin'];
        if (!$viewer->isAdmin()) {
            $hiddenRoleSlugs[] = 'admin';
        }

        return $query->withoutActiveRoles($hiddenRoleSlugs);
    }

    public function isSuperAdmin(): bool
    {
        return $this->activeRoles()->contains(fn (Role $role) => $role->slug === 'superadmin');
    }

    public function hasAnyRole(array $roles): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $allowedSlugs = collect($roles)
            ->filter(fn ($role) => is_string($role) && trim($role) !== '')
            ->map(fn (string $role) => Role::normalizeIdentifierForChecks($role))
            ->unique()
            ->values();

        if ($allowedSlugs->isEmpty()) {
            return false;
        }

        $activeRoleSlugs = $this->activeRoles()->pluck('slug');
        if ($activeRoleSlugs->intersect($allowedSlugs)->isNotEmpty()) {
            return true;
        }

        // Admin is a higher-level role than Supervizor.
        if ($activeRoleSlugs->contains('admin') && $allowedSlugs->contains('supervizor')) {
            return true;
        }

        return false;
    }

    public function isSupervizor(): bool
    {
        return $this->activeRoles()->contains(fn (Role $role) => $role->slug === 'supervizor');
    }

    public function isAdmin(): bool
    {
        return $this->activeRoles()->contains(fn (Role $role) => $role->slug === 'admin');
    }

    public function canSeeUser(self $target): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($target->isSuperAdmin()) {
            return false;
        }

        if ($target->isAdmin() && !$this->isAdmin()) {
            return false;
        }

        return true;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $normalized = Permission::normalizeIdentifierForChecks($permission);
        if ($normalized === '') {
            return false;
        }

        return $this->activeRoles()->contains(function (Role $role) use ($normalized) {
            return $role->permissions->contains(fn (Permission $perm) => $perm->slug === $normalized);
        });
    }

    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $allowed = collect($permissions)
            ->filter(fn ($permission) => is_string($permission) && trim($permission) !== '')
            ->map(fn (string $permission) => Permission::normalizeIdentifierForChecks($permission))
            ->filter()
            ->unique()
            ->values();

        if ($allowed->isEmpty()) {
            return false;
        }

        $userPermissions = $this->activeRoles()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('slug'))
            ->unique();

        return $userPermissions->intersect($allowed)->isNotEmpty();
    }

    public function path($action = 'show')
    {
        return match ($action) {
            'edit' => route('users.edit', $this->id),
            'destroy' => route('users.destroy', $this->id),
            default => route('users.show', $this->id),
        };
    }

    public function primaryActiveRole(): ?Role
    {
        $activeRoles = $this->activeRoles();
        if ($activeRoles->isEmpty()) {
            return null;
        }

        $priority = [
            'superadmin' => 10,
            'admin' => 20,
            'supervizor' => 30,
            'operator-front-office' => 40,
            'financiar' => 50,
            'grafician' => 60,
            'operator-tipografie' => 70,
        ];

        return $activeRoles
            ->sortBy(fn (Role $role) => sprintf('%03d-%s', $priority[$role->slug] ?? 999, strtolower((string) $role->name)))
            ->first();
    }

    private function activeRoles(): Collection
    {
        $this->loadMissing('roles.permissions');

        $today = now((string) config('app.timezone', 'UTC'))->toDateString();

        return $this->roles->filter(function (Role $role) use ($today) {
            $start = $this->roleDateToString($role->pivot?->starts_at);
            $end = $this->roleDateToString($role->pivot?->ends_at);

            if ($start !== null && $start > $today) {
                return false;
            }

            if ($end !== null && $end < $today) {
                return false;
            }

            return true;
        })->values();
    }

    private function roleDateToString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        return Carbon::parse((string) $value, (string) config('app.timezone', 'UTC'))->toDateString();
    }
}
