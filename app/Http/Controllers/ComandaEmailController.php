<?php

namespace App\Http\Controllers;

use App\Models\Comanda;
use App\Models\ComandaFactura;
use App\Models\ComandaEmailLog;
use App\Models\EmailTemplate;
use App\Models\Mockup;
use App\Support\EmailContent;
use App\Support\EmailPlaceholders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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
            'mockupuri' => fn ($query) => $query->latest(),
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
            'mockupTypes' => Mockup::typeOptions(),
        ]);
    }

    public function send(Request $request, Comanda $comanda)
    {
        $data = $request->validate([
            'template_id' => ['nullable', 'integer', 'exists:email_templates,id'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'link_documents' => ['nullable', 'array'],
            'link_documents.*' => ['string', Rule::in(['factura', 'oferta', 'gdpr'])],
            'mockup_link_types' => ['nullable', 'array'],
            'mockup_link_types.*' => ['string', Rule::in(array_keys(Mockup::typeOptions()))],
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
        $selectedDocuments = $this->sanitizeLinkDocuments($data['link_documents'] ?? []);
        $documentLinks = $this->resolveSelectedDocumentLinks($comanda, $selectedDocuments);
        $mockupLinks = $this->resolveSelectedMockupLinks($comanda, $data['mockup_link_types'] ?? []);
        $downloadLinks = array_merge($documentLinks['links'], $mockupLinks['links']);

        try {
            Mail::send('emails.comenzi.generic', [
                'comanda' => $comanda,
                'bodyHtml' => $bodyHtml,
                'downloadLinks' => $downloadLinks,
            ], function ($message) use ($recipient, $subject) {
                $message->to($recipient)->subject($subject);
            });

            $documentValue = count($documentLinks['sent_documents']) === 0
                ? 'none'
                : (count($documentLinks['sent_documents']) === 1
                    ? $documentLinks['sent_documents'][0]
                    : 'multiple');
            $emailLogType = $documentValue === 'none' ? 'generic' : $documentValue;

            ComandaEmailLog::create([
                'comanda_id' => $comanda->id,
                'sent_by' => $request->user()?->id,
                'recipient' => $recipient,
                'subject' => $subject,
                'body' => $bodyHtml,
                'type' => $emailLogType,
                'meta' => [
                    'document' => $documentValue,
                    'documents' => $documentLinks['sent_documents'],
                    'facturi' => $documentLinks['facturi_snapshot'],
                    'skipped_documents' => $documentLinks['skipped_documents'],
                    'info_links' => $mockupLinks['snapshot'],
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

        $message = 'Emailul a fost trimis.';
        if ($documentLinks['skipped_documents'] !== []) {
            $message .= ' Unele linkuri nu au fost adaugate: ' . implode('; ', $documentLinks['skipped_documents']) . '.';
        }

        return back()->with('success', $message);
    }

    private function resolveSelectedDocumentLinks(Comanda $comanda, array $documents): array
    {
        $documents = $this->sanitizeLinkDocuments($documents);
        if ($documents === []) {
            return [
                'links' => [],
                'sent_documents' => [],
                'facturi_snapshot' => [],
                'skipped_documents' => [],
            ];
        }

        $links = [];
        $sentDocuments = [];
        $facturiSnapshot = [];
        $skippedDocuments = [];

        if (in_array('factura', $documents, true)) {
            $facturi = $comanda->facturi;
            if ($facturi->isEmpty()) {
                $skippedDocuments[] = 'Factura (nu exista facturi incarcate)';
            } else {
                $links = array_merge($links, $this->buildFacturaLinks($comanda, $facturi));
                $facturiSnapshot = $facturi->map(fn (ComandaFactura $factura) => [
                    'id' => $factura->id,
                    'original_name' => $factura->original_name,
                    'path' => $factura->path,
                    'mime' => $factura->mime,
                    'size' => $factura->size,
                ])->values()->all();
                $sentDocuments[] = 'factura';
            }
        }

        if (in_array('oferta', $documents, true)) {
                $links[] = [
                    'label' => "oferta-comanda-{$comanda->id}.pdf",
                    'url' => URL::temporarySignedRoute(
                        'comenzi.pdf.oferta.signed',
                        now()->addDays(30),
                        ['comanda' => $comanda->id]
                ),
            ];
            $sentDocuments[] = 'oferta';
        }

        if (in_array('gdpr', $documents, true)) {
            $consent = $comanda->gdprConsents->first();
            if (!$consent) {
                $skippedDocuments[] = 'GDPR (nu exista acord inregistrat)';
            } else {
                $links[] = [
                    'label' => "gdpr-comanda-{$comanda->id}.pdf",
                    'url' => URL::temporarySignedRoute(
                        'comenzi.pdf.gdpr.signed',
                        now()->addDays(30),
                        ['comanda' => $comanda->id]
                    ),
                ];
                $sentDocuments[] = 'gdpr';
            }
        }

        return [
            'links' => $links,
            'sent_documents' => $sentDocuments,
            'facturi_snapshot' => $facturiSnapshot,
            'skipped_documents' => $skippedDocuments,
        ];
    }

    private function sanitizeLinkDocuments(array $documents): array
    {
        return collect($documents)
            ->map(fn ($value) => (string) $value)
            ->filter(fn (string $value) => in_array($value, ['factura', 'oferta', 'gdpr'], true))
            ->unique()
            ->values()
            ->all();
    }

    private function resolveSelectedMockupLinks(Comanda $comanda, array $mockupTypes): array
    {
        $mockupTypes = $this->sanitizeMockupLinkTypes($mockupTypes);
        if ($mockupTypes === []) {
            return ['links' => [], 'snapshot' => []];
        }

        $includesInfoMockup = in_array(Mockup::TIP_INFO_MOCKUP, $mockupTypes, true);
        $otherTypes = array_values(array_filter($mockupTypes, fn (string $type) => $type !== Mockup::TIP_INFO_MOCKUP));

        $query = $comanda->mockupuri()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->where(function ($builder) use ($includesInfoMockup, $otherTypes) {
                if ($otherTypes !== []) {
                    $builder->whereIn('tip', $otherTypes);
                }

                if ($includesInfoMockup) {
                    $builder->orWhere('tip', Mockup::TIP_INFO_MOCKUP)
                        ->orWhereNull('tip');
                }
            });

        $disk = Storage::disk('public');
        $typeOptions = Mockup::typeOptions();
        $linksByType = [];
        $snapshotByType = [];

        foreach ($query->get() as $mockup) {
            $type = $mockup->tip ?: Mockup::TIP_INFO_MOCKUP;
            if (!in_array($type, $mockupTypes, true) || isset($linksByType[$type])) {
                continue;
            }

            if (!$mockup->path || !$disk->exists($mockup->path)) {
                continue;
            }

            $typeLabel = $typeOptions[$type] ?? 'Info';
            $fileLabel = $mockup->original_name ?: ('Fisier #' . $mockup->id);

            $linksByType[$type] = [
                'label' => $fileLabel,
                'url' => URL::temporarySignedRoute(
                    'comenzi.mockupuri.public-download',
                    now()->addDays(30),
                    ['comanda' => $comanda->id, 'mockup' => $mockup->id]
                ),
            ];

            $snapshotByType[$type] = [
                'id' => $mockup->id,
                'type' => $type,
                'type_label' => $typeLabel,
                'original_name' => $mockup->original_name,
            ];

            if (count($linksByType) === count($mockupTypes)) {
                break;
            }
        }

        $links = [];
        $snapshot = [];
        foreach ($mockupTypes as $type) {
            if (isset($linksByType[$type])) {
                $links[] = $linksByType[$type];
                $snapshot[] = $snapshotByType[$type];
            }
        }

        return [
            'links' => $links,
            'snapshot' => $snapshot,
        ];
    }

    private function sanitizeMockupLinkTypes(array $mockupTypes): array
    {
        $allowed = array_keys(Mockup::typeOptions());

        return collect($mockupTypes)
            ->map(fn ($value) => (string) $value)
            ->filter(fn (string $value) => in_array($value, $allowed, true))
            ->unique()
            ->values()
            ->all();
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
                'meta' => array_merge(
                    [
                        'pdf_name' => $email->pdf_name,
                    ],
                    is_array($email->meta) ? $email->meta : []
                ),
            ]))
            ->merge($comanda->facturaEmails->map(fn ($email) => [
                'type' => 'factura',
                'label' => 'Factura',
                'created_at' => $email->created_at,
                'recipient' => $email->recipient,
                'subject' => $email->subject,
                'body' => $email->body,
                'sent_by' => $email->sentBy?->name,
                'meta' => array_merge(
                    [
                        'facturi' => $email->facturi,
                    ],
                    is_array($email->meta) ? $email->meta : []
                ),
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
                'label' => $factura->original_name ?: ("factura-{$factura->id}.pdf"),
                'url' => URL::temporarySignedRoute(
                    'comenzi.facturi.public-download',
                    now()->addDays(30),
                    ['comanda' => $comanda->id, 'factura' => $factura->id]
                ),
            ];
        })->values()->all();
    }
}
