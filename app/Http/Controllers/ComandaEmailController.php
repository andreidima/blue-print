<?php

namespace App\Http\Controllers;

use App\Mail\ComandaFacturaMail;
use App\Models\Comanda;
use App\Models\ComandaFactura;
use App\Models\ComandaFacturaEmail;
use App\Models\ComandaOfertaEmail;
use App\Models\ComandaEmailLog;
use App\Models\EmailTemplate;
use App\Support\EmailContent;
use App\Support\EmailPlaceholders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Throwable;

class ComandaEmailController extends Controller
{
    public function __construct()
    {
        $this->middleware('checkUserPermission:comenzi.email.send')->only(['show', 'send', 'history']);
    }

    public function show(Request $request, Comanda $comanda)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        $comanda->load([
            'client',
            'produse.produs',
            'facturi' => fn ($query) => $query->latest(),
            'gdprConsents' => fn ($query) => $query->latest('signed_at'),
        ]);

        $emailTemplates = EmailTemplate::query()
            ->active()
            ->orderBy('name')
            ->get();

        if ($emailTemplates->isEmpty()) {
            $emailTemplates = EmailTemplate::query()->orderBy('name')->get();
        }

        $placeholders = EmailPlaceholders::forComanda($comanda);

        $defaultTemplate = $emailTemplates->first();
        $defaultTemplateId = $defaultTemplate?->id;
        $defaultSubject = $defaultTemplate
            ? EmailContent::replacePlaceholders($defaultTemplate->subject, $placeholders)
            : '';
        $defaultBody = $defaultTemplate
            ? EmailContent::replacePlaceholders($defaultTemplate->body_html, $placeholders)
            : '';

        return view('comenzi.email', [
            'comanda' => $comanda,
            'emailTemplates' => $emailTemplates,
            'placeholders' => $placeholders,
            'defaultTemplateId' => $defaultTemplateId,
            'defaultSubject' => $defaultSubject,
            'defaultBody' => $defaultBody,
        ]);
    }

    public function send(Request $request, Comanda $comanda)
    {
        $data = $request->validate([
            'template_id' => ['nullable', 'integer', 'exists:email_templates,id'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'document_type' => ['nullable', Rule::in(['none', 'factura', 'oferta', 'gdpr'])],
        ]);

        $recipient = optional($comanda->client)->email;
        if (!$recipient) {
            return back()->with('warning', 'Clientul nu are un email setat.');
        }

        $comanda->load([
            'client',
            'produse.produs',
            'facturi' => fn ($query) => $query->latest(),
            'gdprConsents' => fn ($query) => $query->latest('signed_at'),
        ]);

        $placeholders = EmailPlaceholders::forComanda($comanda);
        $subject = EmailContent::replacePlaceholders($data['subject'], $placeholders);
        $bodyHtml = EmailContent::formatBody($data['body'], $placeholders);

        $documentType = $data['document_type'] ?? 'none';

        try {
            if ($documentType === 'factura') {
                $facturi = $comanda->facturi;
                if ($facturi->isEmpty()) {
                    return back()->with('warning', 'Nu exista facturi de trimis.');
                }

                $downloadLinks = $this->buildFacturaLinks($comanda, $facturi);

                Mail::to($recipient)->send(
                    new ComandaFacturaMail($comanda, $subject, $bodyHtml, $downloadLinks)
                );

                $facturiSnapshot = $facturi->map(fn (ComandaFactura $factura) => [
                    'id' => $factura->id,
                    'original_name' => $factura->original_name,
                    'path' => $factura->path,
                    'mime' => $factura->mime,
                    'size' => $factura->size,
                ])->values()->all();

                ComandaFacturaEmail::create([
                    'comanda_id' => $comanda->id,
                    'sent_by' => $request->user()?->id,
                    'recipient' => $recipient,
                    'subject' => $subject,
                    'body' => $bodyHtml,
                    'facturi' => $facturiSnapshot,
                ]);

                return back()->with('success', 'Emailul cu factura a fost trimis.');
            }

            if ($documentType === 'oferta') {
                $downloadUrl = URL::temporarySignedRoute(
                    'comenzi.pdf.oferta.signed',
                    now()->addDays(30),
                    ['comanda' => $comanda->id]
                );

                Mail::send('emails.comenzi.oferta', [
                    'comanda' => $comanda,
                    'bodyHtml' => $bodyHtml,
                    'downloadUrl' => $downloadUrl,
                ], function ($message) use ($recipient, $subject) {
                    $message->to($recipient)->subject($subject);
                });

                ComandaOfertaEmail::create([
                    'comanda_id' => $comanda->id,
                    'sent_by' => $request->user()?->id,
                    'recipient' => $recipient,
                    'subject' => $subject,
                    'body' => $bodyHtml,
                    'pdf_name' => "oferta-comanda-{$comanda->id}.pdf",
                    'privacy_notice_sent_at' => now(),
                ]);

                return back()->with('success', 'Oferta PDF a fost trimisa pe email.');
            }

            if ($documentType === 'gdpr') {
                $consent = $comanda->gdprConsents->first();
                if (!$consent) {
                    return back()->with('warning', 'Nu exista un acord GDPR inregistrat.');
                }

                $downloadUrl = URL::temporarySignedRoute(
                    'comenzi.pdf.gdpr.signed',
                    now()->addDays(30),
                    ['comanda' => $comanda->id]
                );

                Mail::send('emails.comenzi.gdpr', [
                    'comanda' => $comanda,
                    'bodyHtml' => $bodyHtml,
                    'downloadUrl' => $downloadUrl,
                ], function ($message) use ($recipient, $subject) {
                    $message->to($recipient)->subject($subject);
                });

                ComandaEmailLog::create([
                    'comanda_id' => $comanda->id,
                    'sent_by' => $request->user()?->id,
                    'recipient' => $recipient,
                    'subject' => $subject,
                    'body' => $bodyHtml,
                    'type' => 'gdpr',
                    'meta' => [
                        'document' => 'gdpr',
                    ],
                ]);

                return back()->with('success', 'Acordul GDPR a fost trimis pe email.');
            }

            Mail::send('emails.comenzi.generic', [
                'comanda' => $comanda,
                'bodyHtml' => $bodyHtml,
            ], function ($message) use ($recipient, $subject) {
                $message->to($recipient)->subject($subject);
            });

            ComandaEmailLog::create([
                'comanda_id' => $comanda->id,
                'sent_by' => $request->user()?->id,
                'recipient' => $recipient,
                'subject' => $subject,
                'body' => $bodyHtml,
                'type' => 'generic',
                'meta' => [
                    'document' => 'none',
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Trimitere email esuata.', [
                'comanda_id' => $comanda->id,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            return back()->with('warning', 'Trimiterea emailului a esuat.');
        }

        return back()->with('success', 'Emailul a fost trimis.');
    }

    public function history(Request $request, Comanda $comanda)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        $comanda->load([
            'client',
            'ofertaEmails' => fn ($query) => $query->latest(),
            'ofertaEmails.sentBy',
            'facturaEmails' => fn ($query) => $query->latest(),
            'facturaEmails.sentBy',
            'emailLogs' => fn ($query) => $query->latest(),
            'emailLogs.sentBy',
        ]);

        $entries = collect()
            ->merge($comanda->ofertaEmails->map(fn ($email) => [
                'type' => 'oferta',
                'label' => 'Oferta',
                'created_at' => $email->created_at,
                'recipient' => $email->recipient,
                'subject' => $email->subject,
                'body' => $email->body,
                'sent_by' => $email->sentBy?->name,
                'meta' => [
                    'pdf_name' => $email->pdf_name,
                ],
            ]))
            ->merge($comanda->facturaEmails->map(fn ($email) => [
                'type' => 'factura',
                'label' => 'Factura',
                'created_at' => $email->created_at,
                'recipient' => $email->recipient,
                'subject' => $email->subject,
                'body' => $email->body,
                'sent_by' => $email->sentBy?->name,
                'meta' => [
                    'facturi' => $email->facturi,
                ],
            ]))
            ->merge($comanda->emailLogs->map(fn ($email) => [
                'type' => $email->type,
                'label' => match ($email->type) {
                    'gdpr' => 'GDPR',
                    'generic' => 'Generic',
                    default => ucfirst($email->type),
                },
                'created_at' => $email->created_at,
                'recipient' => $email->recipient,
                'subject' => $email->subject,
                'body' => $email->body,
                'sent_by' => $email->sentBy?->name,
                'meta' => $email->meta ?? [],
            ]))
            ->sortByDesc(fn ($entry) => $entry['created_at'])
            ->values();

        return view('comenzi.email-history', [
            'comanda' => $comanda,
            'emailEntries' => $entries,
        ]);
    }

    private function buildFacturaLinks(Comanda $comanda, $facturi): array
    {
        return $facturi->map(function (ComandaFactura $factura) use ($comanda) {
            return [
                'label' => $factura->original_name ?: 'Factura',
                'url' => URL::temporarySignedRoute(
                    'comenzi.facturi.public-download',
                    now()->addDays(30),
                    ['comanda' => $comanda->id, 'factura' => $factura->id]
                ),
            ];
        })->values()->all();
    }
}
