@extends('layouts.app')

@section('content')
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-4">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-clock-rotate-left"></i> Jurnale cronjob
            </span>
        </div>
        <div class="col-lg-8">
            <form class="needs-validation" novalidate method="GET" action="{{ url()->current() }}">
                <div class="row mb-1 custom-search-form justify-content-end">
                    <div class="col-lg-5">
                        <input list="jobList" type="text" class="form-control rounded-3" id="job" name="job" placeholder="Nume job" value="{{ $jobFilter }}">
                        @if ($knownJobs)
                            <datalist id="jobList">
                                @foreach ($knownJobs as $jobName)
                                    <option value="{{ $jobName }}"></option>
                                @endforeach
                            </datalist>
                        @endif
                    </div>
                    <div class="col-lg-3">
                        <select class="form-select rounded-3" id="status" name="status">
                            <option value="">Toate statusurile</option>
                            @foreach ($knownStatuses as $status)
                                <option value="{{ $status }}" @selected($status === $statusFilter)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 d-grid">
                        <button class="btn btn-sm btn-primary text-white border border-dark rounded-3" type="submit">
                            <i class="fas fa-search text-white me-1"></i>Caută
                        </button>
                    </div>
                    <div class="col-lg-2 d-grid">
                        <a class="btn btn-sm btn-secondary text-white border border-dark rounded-3" href="{{ url()->current() }}" role="button">
                            <i class="far fa-trash-alt text-white me-1"></i>Resetează
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card-body px-0 py-3">
        <div class="table-responsive rounded">
            <table class="table table-striped table-hover rounded" aria-label="Tabela jurnale cronjob">
                <thead class="text-white rounded">
                    <tr class="thead-danger" style="padding:2rem">
                        <th scope="col" class="text-white culoare2" width="20%"><i class="fa-solid fa-calendar-day me-1"></i> Rulat la</th>
                        <th scope="col" class="text-white culoare2" width="20%"><i class="fa-solid fa-code-branch me-1"></i> Job</th>
                        <th scope="col" class="text-white culoare2" width="10%"><i class="fa-solid fa-circle-dot me-1"></i> Status</th>
                        <th scope="col" class="text-white culoare2" width="40%"><i class="fa-solid fa-message me-1"></i> Rezultat</th>
                        <th scope="col" class="text-white culoare2" width="10%"><i class="fa-solid fa-clock me-1"></i> Înregistrat</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ optional($log->ran_at)->format('d.m.Y H:i:s') ?? '—' }}</td>
                            <td>{{ $log->job_name }}</td>
                            <td>
                                @php
                                    $status = $log->status ?? 'necunoscut';
                                    $badgeClass = match (strtolower($status)) {
                                        'success', 'ok', 'done' => 'bg-success',
                                        'failed', 'error' => 'bg-danger',
                                        'running', 'processing' => 'bg-warning text-dark',
                                        default => 'bg-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ ucfirst($status) }}</span>
                            </td>
                            <td class="text-wrap">{{ $log->details ?? '—' }}</td>
                            <td>{{ optional($log->created_at)->format('d.m.Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="fa-solid fa-robot fa-2x mb-3 d-block"></i>
                                <p class="mb-0">Încă nu există înregistrări pentru cronjoburi.</p>
                                <p class="small mb-0 mt-2">Verifică din nou după ce joburile programate vor rula.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <nav>
            <ul class="pagination justify-content-center">
                {{ $logs->links() }}
            </ul>
        </nav>
    </div>
</div>
@endsection
