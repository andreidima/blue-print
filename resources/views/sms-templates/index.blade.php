@extends ('layouts.app')

@section('content')
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-6">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-comment-sms me-1"></i> Template-uri SMS
            </span>
        </div>
        <div class="col-lg-6 text-end">
            <a class="btn btn-sm btn-success text-white border border-dark rounded-3 me-2" href="{{ route('sms-templates.create') }}">
                <i class="fa-solid fa-plus me-1"></i> Adauga
            </a>
            <a class="btn btn-sm btn-secondary text-white border border-dark rounded-3" href="{{ Session::get('returnUrl', route('comenzi.index')) }}">
                <i class="fa-solid fa-arrow-left me-1"></i> Inapoi
            </a>
        </div>
    </div>

    <div class="card-body px-3 py-4">
        @include ('errors.errors')

        <div class="table-responsive rounded">
            <table class="table table-striped table-hover rounded" aria-label="Templateuri SMS">
                <thead class="text-white rounded">
                    <tr class="thead-danger">
                        <th scope="col" class="text-white culoare2 text-nowrap">Nume</th>
                        <th scope="col" class="text-white culoare2 text-nowrap">Cheie</th>
                        <th scope="col" class="text-white culoare2 text-nowrap">Activ</th>
                        <th scope="col" class="text-white culoare2 text-nowrap">Mesaj</th>
                        <th scope="col" class="text-white culoare2 text-nowrap text-end">Actiuni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($smsTemplates as $template)
                        <tr>
                            <td>{{ $template->name }}</td>
                            <td><code>{{ $template->key }}</code></td>
                            <td>
                                <span class="badge {{ $template->active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $template->active ? 'Da' : 'Nu' }}
                                </span>
                            </td>
                            <td>{{ \Illuminate\Support\Str::limit($template->body, 140) }}</td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <a class="btn btn-sm btn-primary text-white" href="{{ route('sms-templates.edit', $template) }}">
                                        <i class="fa-solid fa-pen me-1"></i> Editeaza
                                    </a>
                                    <form method="POST" action="{{ route('sms-templates.destroy', $template) }}" onsubmit="return confirm('Sigur vrei sa stergi acest template?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger text-white">
                                            <i class="fa-solid fa-trash me-1"></i> Sterge
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Nu exista template-uri SMS.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
