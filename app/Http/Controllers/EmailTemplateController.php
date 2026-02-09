<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Support\EmailContent;
use App\Support\EmailPlaceholders;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmailTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('checkUserPermission:email-templates.write')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request)
    {
        $request->session()->forget('returnUrl');

        $emailTemplates = EmailTemplate::query()
            ->orderBy('name')
            ->get();

        return view('email-templates.index', compact('emailTemplates'));
    }

    public function create(Request $request)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        $placeholders = EmailPlaceholders::reference();

        return view('email-templates.create', compact('placeholders'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'color' => ['required', 'string', 'max:20'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active');
        $data['key'] = $this->generateKey($data['name']);
        $data['body_html'] = EmailContent::sanitizeHtml($data['body_html']);

        EmailTemplate::create($data);

        return redirect($request->session()->get('returnUrl', route('email-templates.index')))
            ->with('success', 'Template-ul email a fost adaugat.');
    }

    public function edit(Request $request, EmailTemplate $emailTemplate)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        $placeholders = EmailPlaceholders::reference();

        return view('email-templates.edit', compact('emailTemplate', 'placeholders'));
    }

    public function update(Request $request, EmailTemplate $emailTemplate)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'color' => ['required', 'string', 'max:20'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active');
        $data['body_html'] = EmailContent::sanitizeHtml($data['body_html']);

        $emailTemplate->update($data);

        return redirect($request->session()->get('returnUrl', route('email-templates.index')))
            ->with('status', 'Template-ul email a fost actualizat.');
    }

    public function destroy(EmailTemplate $emailTemplate)
    {
        $emailTemplate->delete();

        return redirect()->route('email-templates.index')
            ->with('status', 'Template-ul email a fost sters.');
    }

    private function generateKey(string $name): string
    {
        $base = Str::slug($name, '_');
        if ($base === '') {
            $base = 'template';
        }

        $key = $base;
        $suffix = 2;

        while (EmailTemplate::where('key', $key)->exists()) {
            $key = $base . '_' . $suffix;
            $suffix++;
        }

        return $key;
    }
}
