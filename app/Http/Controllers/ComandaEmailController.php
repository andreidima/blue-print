<?php

namespace App\Http\Controllers;

use App\Models\Comanda;
use App\Models\ComandaGdprConsent;
use App\Models\ComandaFactura;
use App\Models\ComandaEmailLog;
use App\Models\ComandaFacturaEmail;
use App\Models\ComandaOfertaEmail;
use App\Models\EmailTemplate;
use App\Models\Mockup;
use App\Support\ComandaEmailAttachmentSupport;
use App\Support\ComandaPdfFactory;
use App\Support\ClientEmailSupport;
use App\Support\EmailContent;
use App\Support\EmailPlaceholders;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ComandaEmailController extends Controller
{
    public function __construct()
    {
        $this->middleware('checkUserPermission:comenzi.email.send')->only([
            'show',
            'send',
            'history',
            'viewHistoryAttachment',
            'downloadHistoryAttachment',
        ]);
    }

    public function show(Request $request, Comanda $comanda)
    {
        $this->rememberReturnUrl($request);
        $canAccessOfertaPrices = $comanda->canAccessOfertaPrices($request->user());

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
            ? EmailContent::replacePlaceholders(
                EmailContent::repairMisencodedUtf8($defaultTemplate->body_html),
                $placeholders
            )
            : '';

        return view('comenzi.email', [
            'comanda' => $comanda,
            'emailTemplates' => $emailTemplates,
            'placeholders' => $placeholders,
            'defaultTemplateId' => $defaultTemplateId,
            'defaultSubject' => $defaultSubject,
            'defaultBody' => $defaultBody,
            'canAccessOfertaPrices' => $canAccessOfertaPrices,
            'mockupTypes' => Mockup::typeOptions(),
        ]);
    }

    public function send(Request $request, Comanda $comanda)
    {
        $canAccessOfertaPrices = $comanda->canAccessOfertaPrices($request->user());
        $data = $request->validate([
            'template_id' => ['nullable', 'integer', 'exists:email_templates,id'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'link_documents' => ['nullable', 'array'],
            'link_documents.*' => ['string', Rule::in(['factura', 'oferta', 'gdpr'])],
            'mockup_link_types' => ['nullable', 'array'],
            'mockup_link_types.*' => ['string', Rule::in(array_keys(Mockup::typeOptions()))],
        ]);

        $recipients = $comanda->client?->email_addresses ?? [];
        if ($recipients === []) {
            return back()->with('warning', 'Clientul nu are emailuri setate.');
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
        $documentLinks = $this->resolveSelectedDocumentLinks($comanda, $selectedDocuments, $canAccessOfertaPrices);
        $mockupLinks = $this->resolveSelectedMockupLinks($comanda, $data['mockup_link_types'] ?? []);
        $downloadLinks = array_merge($documentLinks['links'], $mockupLinks['links']);

        try {
            Mail::send('emails.comenzi.generic', [
                'comanda' => $comanda,
                'bodyHtml' => $bodyHtml,
                'downloadLinks' => $downloadLinks,
            ], function ($message) use ($recipients, $subject) {
                $message->to($recipients)->subject($subject);
            });
        } catch (Throwable $e) {
            Log::error('Trimitere email esuata.', [
                'comanda_id' => $comanda->id,
                'recipient' => ClientEmailSupport::format($recipients),
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            return back()->with('warning', 'Trimiterea emailului a esuat.');
        }

        [$attachments, $snapshotWarnings] = $this->buildGenericEmailSnapshots(
            $comanda,
            $documentLinks['sent_documents'],
            $mockupLinks['snapshot']
        );

        $documentValue = count($documentLinks['sent_documents']) === 0
            ? 'none'
            : (count($documentLinks['sent_documents']) === 1
                ? $documentLinks['sent_documents'][0]
                : 'multiple');
        $emailLogType = $documentValue === 'none' ? 'generic' : $documentValue;

        ComandaEmailLog::create([
            'comanda_id' => $comanda->id,
            'sent_by' => $request->user()?->id,
            'recipient' => ClientEmailSupport::format($recipients),
            'subject' => $subject,
            'body' => $bodyHtml,
            'type' => $emailLogType,
            'meta' => [
                'recipients' => $recipients,
                'attachments' => $attachments,
                'document' => $documentValue,
                'documents' => $documentLinks['sent_documents'],
                'facturi' => $documentLinks['facturi_snapshot'],
                'skipped_documents' => $documentLinks['skipped_documents'],
                'info_links' => $mockupLinks['snapshot'],
            ],
        ]);

        $message = 'Emailul a fost trimis.';
        if ($documentLinks['skipped_documents'] !== []) {
            $message .= ' Unele linkuri nu au fost adaugate: ' . implode('; ', $documentLinks['skipped_documents']) . '.';
        }
        if ($snapshotWarnings !== []) {
            $message .= ' Unele snapshot-uri nu au putut fi salvate: ' . implode('; ', $snapshotWarnings) . '.';
        }

        return back()->with('success', $message);
    }

    private function resolveSelectedDocumentLinks(Comanda $comanda, array $documents, bool $canAccessOfertaPrices = true): array
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
            if (!$canAccessOfertaPrices) {
                $skippedDocuments[] = 'Oferta (fara acces la preturi)';
            } else {
                $links[] = [
                    'label' => 'Descarca Oferta',
                    'url' => URL::temporarySignedRoute(
                        'comenzi.pdf.oferta.signed',
                        now()->addDays(30),
                        ['comanda' => $comanda->id]
                    ),
                ];
                $sentDocuments[] = 'oferta';
            }
        }

        if (in_array('gdpr', $documents, true)) {
            $consent = $comanda->gdprConsents->first();
            if (!$consent) {
                $skippedDocuments[] = 'GDPR (nu exista acord inregistrat)';
            } else {
                $links[] = [
                    'label' => 'Descarca GDPR',
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

            $linksByType[$type] = [
                'label' => 'Descarca ' . Str::title($typeLabel),
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
                'path' => $mockup->path,
                'mime' => $mockup->mime,
                'size' => $mockup->size,
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
        $this->rememberReturnUrl($request);

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
            ->merge($comanda->ofertaEmails->map(fn (ComandaOfertaEmail $email) => $this->buildHistoryEntryFromOfertaEmail($comanda, $email)))
            ->merge($comanda->facturaEmails->map(fn (ComandaFacturaEmail $email) => $this->buildHistoryEntryFromFacturaEmail($comanda, $email)))
            ->merge($comanda->emailLogs->map(fn (ComandaEmailLog $email) => $this->buildHistoryEntryFromLog($comanda, $email)))
            ->sortByDesc(fn ($entry) => $entry['created_at'])
            ->values();

        return view('comenzi.email-history', [
            'comanda' => $comanda,
            'emailEntries' => $entries,
        ]);
    }

    public function viewHistoryAttachment(
        Request $request,
        Comanda $comanda,
        string $sourceType,
        int $emailEntry,
        string $attachmentKey
    ) {
        return $this->respondWithHistoryAttachment($request, $comanda, $sourceType, $emailEntry, $attachmentKey, false);
    }

    public function downloadHistoryAttachment(
        Request $request,
        Comanda $comanda,
        string $sourceType,
        int $emailEntry,
        string $attachmentKey
    ) {
        return $this->respondWithHistoryAttachment($request, $comanda, $sourceType, $emailEntry, $attachmentKey, true);
    }

    private function buildFacturaLinks(Comanda $comanda, $facturi): array
    {
        $totalFacturi = $facturi->count();

        return $facturi->values()->map(function (ComandaFactura $factura, int $index) use ($comanda, $totalFacturi) {
            $label = $totalFacturi > 1
                ? 'Descarca Factura #' . ($index + 1)
                : 'Descarca Factura';

            return [
                'label' => $label,
                'url' => URL::temporarySignedRoute(
                    'comenzi.facturi.public-download',
                    now()->addDays(30),
                    ['comanda' => $comanda->id, 'factura' => $factura->id]
                ),
            ];
        })->values()->all();
    }

    private function buildGenericEmailSnapshots(Comanda $comanda, array $sentDocuments, array $mockupSnapshots): array
    {
        $attachments = [];
        $warnings = [];

        if (in_array('factura', $sentDocuments, true)) {
            foreach ($comanda->facturi as $factura) {
                $snapshot = ComandaEmailAttachmentSupport::storePublicFileSnapshot(
                    $comanda,
                    ComandaEmailAttachmentSupport::ENTRY_LOG,
                    ComandaEmailAttachmentSupport::KIND_FACTURA,
                    $factura->original_name ?: ('factura-' . $factura->id . '.pdf'),
                    (string) $factura->path,
                    $factura->mime,
                    $factura->size,
                    [
                        'label' => 'Factura',
                        'source_id' => $factura->id,
                    ]
                );

                if ($snapshot) {
                    $attachments[] = $snapshot;
                } else {
                    $warnings[] = 'Factura ' . ($factura->original_name ?: ('#' . $factura->id));
                }
            }
        }

        if (in_array('oferta', $sentDocuments, true)) {
            $fileName = ComandaPdfFactory::ofertaFilename($comanda);
            $snapshot = ComandaEmailAttachmentSupport::storeBinarySnapshot(
                $comanda,
                ComandaEmailAttachmentSupport::ENTRY_LOG,
                ComandaEmailAttachmentSupport::KIND_OFERTA,
                $fileName,
                ComandaPdfFactory::oferta($comanda)->output(),
                'application/pdf',
                ['label' => 'Oferta']
            );

            if ($snapshot) {
                $attachments[] = $snapshot;
            } else {
                $warnings[] = 'Oferta';
            }
        }

        if (in_array('gdpr', $sentDocuments, true)) {
            $consent = $this->resolveLatestGdprConsent($comanda);
            if ($consent) {
                $fileName = ComandaPdfFactory::gdprFilename($comanda);
                $snapshot = ComandaEmailAttachmentSupport::storeBinarySnapshot(
                    $comanda,
                    ComandaEmailAttachmentSupport::ENTRY_LOG,
                    ComandaEmailAttachmentSupport::KIND_GDPR,
                    $fileName,
                    ComandaPdfFactory::gdpr($comanda, $consent)->output(),
                    'application/pdf',
                    ['label' => 'GDPR']
                );

                if ($snapshot) {
                    $attachments[] = $snapshot;
                } else {
                    $warnings[] = 'GDPR';
                }
            }
        }

        foreach ($mockupSnapshots as $mockupSnapshot) {
            if (!is_array($mockupSnapshot) || empty($mockupSnapshot['path'])) {
                continue;
            }

            $typeLabel = trim((string) ($mockupSnapshot['type_label'] ?? 'Info'));
            $snapshot = ComandaEmailAttachmentSupport::storePublicFileSnapshot(
                $comanda,
                ComandaEmailAttachmentSupport::ENTRY_LOG,
                ComandaEmailAttachmentSupport::KIND_MOCKUP,
                (string) ($mockupSnapshot['original_name'] ?? ($typeLabel !== '' ? $typeLabel : 'info')),
                (string) $mockupSnapshot['path'],
                $mockupSnapshot['mime'] ?? null,
                isset($mockupSnapshot['size']) ? (int) $mockupSnapshot['size'] : null,
                [
                    'label' => $typeLabel !== '' ? $typeLabel : 'Info',
                    'source_id' => isset($mockupSnapshot['id']) ? (int) $mockupSnapshot['id'] : null,
                    'type_label' => $typeLabel !== '' ? $typeLabel : null,
                ]
            );

            if ($snapshot) {
                $attachments[] = $snapshot;
            } else {
                $warnings[] = 'Info ' . ((string) ($mockupSnapshot['original_name'] ?? ($typeLabel !== '' ? $typeLabel : 'mockup')));
            }
        }

        return [$attachments, $warnings];
    }

    private function buildHistoryEntryFromOfertaEmail(Comanda $comanda, ComandaOfertaEmail $email): array
    {
        $meta = array_merge(
            [
                'pdf_name' => $email->pdf_name,
            ],
            is_array($email->meta) ? $email->meta : []
        );

        return [
            'type' => 'oferta',
            'entry_source' => ComandaEmailAttachmentSupport::ENTRY_OFERTA_EMAIL,
            'entry_id' => $email->id,
            'label' => 'Oferta',
            'created_at' => $email->created_at,
            'recipient' => ClientEmailSupport::format(ClientEmailSupport::recipientsFromMeta($email->recipient, $meta)),
            'recipients' => ClientEmailSupport::recipientsFromMeta($email->recipient, $meta),
            'subject' => $email->subject,
            'body' => $email->body,
            'sent_by' => $email->sentBy?->name,
            'meta' => $meta,
            'attachments' => ComandaEmailAttachmentSupport::buildHistoryAttachments(
                $comanda,
                ComandaEmailAttachmentSupport::ENTRY_OFERTA_EMAIL,
                $email->id,
                $meta,
                ['pdf_name' => $email->pdf_name]
            ),
        ];
    }

    private function buildHistoryEntryFromFacturaEmail(Comanda $comanda, ComandaFacturaEmail $email): array
    {
        $meta = array_merge(
            [
                'facturi' => $email->facturi,
            ],
            is_array($email->meta) ? $email->meta : []
        );

        return [
            'type' => 'factura',
            'entry_source' => ComandaEmailAttachmentSupport::ENTRY_FACTURA_EMAIL,
            'entry_id' => $email->id,
            'label' => 'Factura',
            'created_at' => $email->created_at,
            'recipient' => ClientEmailSupport::format(ClientEmailSupport::recipientsFromMeta($email->recipient, $meta)),
            'recipients' => ClientEmailSupport::recipientsFromMeta($email->recipient, $meta),
            'subject' => $email->subject,
            'body' => $email->body,
            'sent_by' => $email->sentBy?->name,
            'meta' => $meta,
            'attachments' => ComandaEmailAttachmentSupport::buildHistoryAttachments(
                $comanda,
                ComandaEmailAttachmentSupport::ENTRY_FACTURA_EMAIL,
                $email->id,
                $meta,
                ['facturi' => is_array($email->facturi) ? $email->facturi : []]
            ),
        ];
    }

    private function buildHistoryEntryFromLog(Comanda $comanda, ComandaEmailLog $email): array
    {
        $meta = is_array($email->meta) ? $email->meta : [];

        return [
            'type' => $email->type,
            'entry_source' => ComandaEmailAttachmentSupport::ENTRY_LOG,
            'entry_id' => $email->id,
            'label' => match ($email->type) {
                'gdpr' => 'GDPR',
                'generic' => 'Generic',
                default => ucfirst($email->type),
            },
            'created_at' => $email->created_at,
            'recipient' => ClientEmailSupport::format(ClientEmailSupport::recipientsFromMeta($email->recipient, $meta)),
            'recipients' => ClientEmailSupport::recipientsFromMeta($email->recipient, $meta),
            'subject' => $email->subject,
            'body' => $email->body,
            'sent_by' => $email->sentBy?->name,
            'meta' => $meta,
            'attachments' => ComandaEmailAttachmentSupport::buildHistoryAttachments(
                $comanda,
                ComandaEmailAttachmentSupport::ENTRY_LOG,
                $email->id,
                $meta
            ),
        ];
    }

    private function respondWithHistoryAttachment(
        Request $request,
        Comanda $comanda,
        string $sourceType,
        int $emailEntry,
        string $attachmentKey,
        bool $download
    ) {
        $entry = ComandaEmailAttachmentSupport::resolveHistoryEntry($sourceType, $comanda, $emailEntry);
        abort_unless($entry instanceof Model, 404);

        $attachment = ComandaEmailAttachmentSupport::findAttachmentByKey(
            $comanda,
            $sourceType,
            $emailEntry,
            is_array($entry->meta ?? null) ? $entry->meta : [],
            $this->historyAttachmentContext($entry),
            $attachmentKey
        );

        abort_if(!$attachment || empty($attachment['available']), 404);

        return ComandaEmailAttachmentSupport::buildAttachmentResponse($comanda, $attachment, $download);
    }

    private function historyAttachmentContext(Model $entry): array
    {
        if ($entry instanceof ComandaFacturaEmail) {
            return ['facturi' => is_array($entry->facturi) ? $entry->facturi : []];
        }

        if ($entry instanceof ComandaOfertaEmail) {
            return ['pdf_name' => $entry->pdf_name];
        }

        return [];
    }

    private function resolveLatestGdprConsent(Comanda $comanda): ?ComandaGdprConsent
    {
        return $comanda->gdprConsents->first() ?: $comanda->gdprConsents()->latest('signed_at')->first();
    }
}

