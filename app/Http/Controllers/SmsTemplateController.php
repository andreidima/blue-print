<?php

namespace App\Http\Controllers;

use App\Models\SmsTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SmsTemplateController extends Controller
{
    public function index(Request $request)
    {
        $request->session()->forget('returnUrl');

        $smsTemplates = SmsTemplate::query()
            ->orderBy('name')
            ->get();

        return view('sms-templates.index', compact('smsTemplates'));
    }

    public function create(Request $request)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        $placeholders = $this->placeholderReference();

        return view('sms-templates.create', compact('placeholders'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active');
        $data['key'] = $this->generateKey($data['name']);

        SmsTemplate::create($data);

        return redirect($request->session()->get('returnUrl', route('sms-templates.index')))
            ->with('success', 'Template-ul SMS a fost adaugat.');
    }

    public function edit(Request $request, SmsTemplate $smsTemplate)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        $placeholders = $this->placeholderReference();

        return view('sms-templates.edit', compact('smsTemplate', 'placeholders'));
    }

    public function update(Request $request, SmsTemplate $smsTemplate)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active');

        $smsTemplate->update($data);

        return redirect($request->session()->get('returnUrl', route('sms-templates.index')))
            ->with('status', 'Template-ul SMS a fost actualizat.');
    }

    public function destroy(SmsTemplate $smsTemplate)
    {
        $smsTemplate->delete();

        return redirect()->route('sms-templates.index')
            ->with('status', 'Template-ul SMS a fost sters.');
    }

    private function generateKey(string $name): string
    {
        $base = Str::slug($name, '_');
        if ($base === '') {
            $base = 'template';
        }

        $key = $base;
        $suffix = 2;

        while (SmsTemplate::where('key', $key)->exists()) {
            $key = $base . '_' . $suffix;
            $suffix++;
        }

        return $key;
    }

    private function placeholderReference(): array
    {
        return [
            [
                'token' => '{app}',
                'description' => 'Numele aplicatiei',
                'example' => config('app.name'),
            ],
            [
                'token' => '{comanda_id}',
                'description' => 'ID-ul comenzii',
                'example' => '1234',
            ],
            [
                'token' => '{client}',
                'description' => 'Numele clientului',
                'example' => 'Popescu Ion',
            ],
            [
                'token' => '{telefon}',
                'description' => 'Telefon client',
                'example' => '0722 123 456',
            ],
            [
                'token' => '{email}',
                'description' => 'Email client',
                'example' => 'ion.popescu@example.com',
            ],
            [
                'token' => '{total}',
                'description' => 'Total comanda',
                'example' => '250.00',
            ],
            [
                'token' => '{livrare}',
                'description' => 'Data estimata de livrare',
                'example' => '24.01.2026 14:30',
            ],
            [
                'token' => '{finalizat_la}',
                'description' => 'Data finalizarii',
                'example' => '24.01.2026 18:10',
            ],
            [
                'token' => '{status}',
                'description' => 'Status comanda',
                'example' => 'Finalizata',
            ],
            [
                'token' => '{tip}',
                'description' => 'Tip comanda',
                'example' => 'Tipar',
            ],
            [
                'token' => '{sursa}',
                'description' => 'Sursa comanda',
                'example' => 'Online',
            ],
        ];
    }
}
