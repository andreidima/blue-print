@extends ('layouts.app')

@section('content')
@php
    $todayDate = now((string) config('app.timezone', 'UTC'))->toDateString();
    $visibleRoles = $user->roles->where('slug', '!=', 'superadmin');

    $resolveStatus = function (?string $startDate, ?string $endDate) use ($todayDate) {
        if ($startDate !== null && $startDate > $todayDate) {
            return ['label' => 'Programat', 'class' => 'bg-warning text-dark'];
        }

        if ($endDate !== null && $endDate < $todayDate) {
            return ['label' => 'Expirat', 'class' => 'bg-danger'];
        }

        return ['label' => 'Activ', 'class' => 'bg-success'];
    };
@endphp
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="shadow-lg" style="border-radius: 40px;">
                <div class="border border-secondary p-2 culoare2" style="border-radius: 40px 40px 0px 0px;">
                    <span class="badge text-light fs-5">
                        <i class="fa-solid fa-users me-1"></i> Detalii Utilizator
                    </span>
                </div>

                <div class="card-body border border-secondary p-4" style="border-radius: 0px 0px 40px 40px;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong>Name:</strong> {{ $user->name }}
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Email:</strong> <a href="mailto:{{ $user->email }}">{{ $user->email }}</a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Telefon:</strong> {{ $user->telefon }}
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Roluri:</strong>
                            <div class="mt-1 d-flex flex-column gap-2">
                                @forelse ($visibleRoles as $role)
                                    @php
                                        $startAt = $role->pivot?->starts_at ? \Illuminate\Support\Carbon::parse($role->pivot->starts_at) : null;
                                        $endAt = $role->pivot?->ends_at ? \Illuminate\Support\Carbon::parse($role->pivot->ends_at) : null;
                                        $startDate = $startAt?->toDateString();
                                        $endDate = $endAt?->toDateString();
                                        $startDateLabel = $startAt?->format('d.m.Y');
                                        $endDateLabel = $endAt?->format('d.m.Y');
                                        $status = $resolveStatus($startDate, $endDate);
                                    @endphp
                                    <div class="border rounded-3 p-2">
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <span class="badge" style="background-color: {{ $role->color }}; color: #fff;">{{ $role->name }}</span>
                                            <span class="badge {{ $status['class'] }}">{{ $status['label'] }}</span>
                                        </div>
                                        <div class="small text-muted mt-1">
                                            @if ($startDate || $endDate)
                                                Interval: {{ $startDateLabel ?? '-' }} - {{ $endDateLabel ?? '-' }}
                                            @else
                                                Valabilitate: Nelimitat
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <span class="text-muted">-</span>
                                @endforelse
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Stare cont:</strong>
                            @if ($user->activ == 0)
                                <span class="text-danger">Inchis</span>
                            @else
                                <span class="text-success">Deschis</span>
                            @endif
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Creat la:</strong> {{ $user->created_at?->format('d.m.Y H:i') }}
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Ultima modificare:</strong> {{ $user->updated_at?->format('d.m.Y H:i') }}
                        </div>
                    </div>

                    <div class="d-flex justify-content-center mt-4">
                        <a href="{{ route('users.edit', $user->id) }}" class="btn btn-primary text-white me-3 rounded-3">
                            <i class="fa-solid fa-edit me-1"></i> Modifica
                        </a>
                        <a class="btn btn-secondary rounded-3" href="{{ Session::get('returnUrl', route('users.index')) }}">
                            <i class="fa-solid fa-arrow-left me-1"></i> Inapoi
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection
