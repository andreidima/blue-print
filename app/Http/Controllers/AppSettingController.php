<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AppSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('checkUserPermission:app-settings.view')->only(['index', 'create', 'edit']);
        $this->middleware('checkUserPermission:app-settings.write')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request)
    {
        $request->session()->forget('returnUrl');

        $searchKey = $request->searchKey;
        $searchLabel = $request->searchLabel;
        $type = $request->type;
        $sort = (string) $request->get('sort', 'label');
        $dir = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $appSettings = AppSetting::query()
            ->when($searchKey, fn ($query, $value) => $query->where('key', 'like', '%' . $value . '%'))
            ->when($searchLabel, fn ($query, $value) => $query->where('label', 'like', '%' . $value . '%'))
            ->when($type !== null && $type !== '', fn ($query) => $query->where('type', $type))
            ->when($sort === 'label', fn ($query) => $query->orderBy('label', $dir))
            ->when($sort === 'key', fn ($query) => $query->orderBy('key', $dir))
            ->when($sort === 'type', fn ($query) => $query->orderBy('type', $dir))
            ->when($sort === 'updated_at', fn ($query) => $query->orderBy('updated_at', $dir))
            ->when(!in_array($sort, ['label', 'key', 'type', 'updated_at'], true), fn ($query) => $query->orderBy('label'))
            ->orderBy('id')
            ->simplePaginate(25);

        return view('app-settings.index', [
            'appSettings' => $appSettings,
            'searchKey' => $searchKey,
            'searchLabel' => $searchLabel,
            'type' => $type,
            'typeOptions' => AppSetting::typeOptions(),
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(Request $request)
    {
        $this->rememberReturnUrl($request);

        return view('app-settings.create', [
            'typeOptions' => AppSetting::typeOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        AppSetting::create($data);

        return redirect($request->session()->get('returnUrl', route('app-settings.index')))
            ->with('success', 'Setarea a fost adaugata.');
    }

    public function edit(Request $request, AppSetting $appSetting)
    {
        $this->rememberReturnUrl($request);

        return view('app-settings.edit', [
            'appSetting' => $appSetting,
            'typeOptions' => AppSetting::typeOptions(),
        ]);
    }

    public function update(Request $request, AppSetting $appSetting)
    {
        $data = $this->validatedData($request, $appSetting);

        $appSetting->update($data);

        return redirect($request->session()->get('returnUrl', route('app-settings.index')))
            ->with('status', 'Setarea a fost actualizata.');
    }

    public function destroy(AppSetting $appSetting)
    {
        $appSetting->delete();

        return redirect()->route('app-settings.index')
            ->with('status', 'Setarea a fost stearsa.');
    }

    private function validatedData(Request $request, ?AppSetting $appSetting = null): array
    {
        $rules = [
            'label' => ['required', 'string', 'max:150'],
            'key' => [
                'required',
                'string',
                'max:150',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('app_settings', 'key')->ignore($appSetting?->id),
            ],
            'type' => ['required', 'string', Rule::in(array_keys(AppSetting::typeOptions()))],
            'value' => ['nullable', 'string', 'max:10000'],
            'description' => ['nullable', 'string', 'max:255'],
        ];

        $data = $request->validate($rules, [
            'key.regex' => 'Cheia poate contine doar litere mici, cifre, punct, minus sau underscore.',
        ]);

        $data['key'] = trim((string) $data['key']);
        $data['value'] = trim((string) ($data['value'] ?? '')) ?: null;
        $data['description'] = trim((string) ($data['description'] ?? '')) ?: null;

        if ($data['type'] === AppSetting::TYPE_URL && $data['value'] !== null && !filter_var($data['value'], FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'value' => 'Valoarea trebuie sa fie un URL valid.',
            ]);
        }

        return $data;
    }
}
