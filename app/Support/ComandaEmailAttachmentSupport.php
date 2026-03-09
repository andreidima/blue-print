<?php

namespace App\Support;

use App\Models\Comanda;
use App\Models\ComandaAtasament;
use App\Models\ComandaFactura;
use App\Models\ComandaFacturaEmail;
use App\Models\ComandaGdprConsent;
use App\Models\ComandaEmailLog;
use App\Models\ComandaOfertaEmail;
use App\Models\Mockup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ComandaEmailAttachmentSupport
{
    public const ENTRY_FACTURA_EMAIL = 'factura-email';
    public const ENTRY_OFERTA_EMAIL = 'oferta-email';
    public const ENTRY_LOG = 'email-log';

    public const KIND_ATASAMENT = 'atasament';
    public const KIND_FACTURA = 'factura';
    public const KIND_GDPR = 'gdpr';
    public const KIND_MOCKUP = 'mockup';
    public const KIND_OFERTA = 'oferta';

    public static function storePublicFileSnapshot(
        Comanda $comanda,
        string $entrySource,
        string $kind,
        string $originalName,
        string $sourcePath,
        ?string $mime = null,
        ?int $size = null,
        array $extra = []
    ): ?array {
        $disk = Storage::disk('public');
        if (!$disk->exists($sourcePath)) {
            return null;
        }

        $stream = $disk->readStream($sourcePath);
        if ($stream === false) {
            return null;
        }

        $snapshotPath = self::snapshotPath($comanda, $entrySource, $originalName);

        try {
            if ($disk->writeStream($snapshotPath, $stream) === false) {
                return null;
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return self::buildSnapshotPayload(
            $snapshotPath,
            $kind,
            $originalName,
            $mime,
            $size ?? self::safeFileSize($disk, $snapshotPath),
            $extra
        );
    }

    public static function storeBinarySnapshot(
        Comanda $comanda,
        string $entrySource,
        string $kind,
        string $originalName,
        string $contents,
        ?string $mime = null,
        array $extra = []
    ): ?array {
        $disk = Storage::disk('public');
        $snapshotPath = self::snapshotPath($comanda, $entrySource, $originalName);

        if (!$disk->put($snapshotPath, $contents)) {
            return null;
        }

        return self::buildSnapshotPayload(
            $snapshotPath,
            $kind,
            $originalName,
            $mime,
            strlen($contents),
            $extra
        );
    }

    public static function buildHistoryAttachments(
        Comanda $comanda,
        string $entrySource,
        int $entryId,
        array $meta = [],
        array $context = []
    ): array {
        return collect(self::rawHistoryAttachments($meta, $context))
            ->map(function (array $attachment) use ($comanda, $entrySource, $entryId) {
                $available = self::attachmentIsAvailable($comanda, $attachment);

                return array_merge($attachment, [
                    'available' => $available,
                    'view_url' => $available ? route('comenzi.email.history.attachments.view', [
                        'comanda' => $comanda,
                        'sourceType' => $entrySource,
                        'emailEntry' => $entryId,
                        'attachmentKey' => $attachment['key'],
                    ]) : null,
                    'download_url' => $available ? route('comenzi.email.history.attachments.download', [
                        'comanda' => $comanda,
                        'sourceType' => $entrySource,
                        'emailEntry' => $entryId,
                        'attachmentKey' => $attachment['key'],
                    ]) : null,
                ]);
            })
            ->values()
            ->all();
    }

    public static function collectSentSourceIds(Comanda $comanda, string $kind): array
    {
        $entries = collect()
            ->merge(($comanda->facturaEmails ?? collect())->map(fn (ComandaFacturaEmail $email) => [
                'meta' => is_array($email->meta) ? $email->meta : [],
                'context' => ['facturi' => is_array($email->facturi) ? $email->facturi : []],
            ]))
            ->merge(($comanda->ofertaEmails ?? collect())->map(fn (ComandaOfertaEmail $email) => [
                'meta' => is_array($email->meta) ? $email->meta : [],
                'context' => ['pdf_name' => $email->pdf_name],
            ]))
            ->merge(($comanda->emailLogs ?? collect())->map(fn (ComandaEmailLog $email) => [
                'meta' => is_array($email->meta) ? $email->meta : [],
                'context' => [],
            ]));

        return $entries
            ->flatMap(function (array $entry) use ($kind) {
                return collect(self::rawHistoryAttachments($entry['meta'], $entry['context']))
                    ->where('kind', $kind)
                    ->pluck('source_id');
            })
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    public static function resolveHistoryEntry(string $sourceType, Comanda $comanda, int $entryId): ?Model
    {
        return match ($sourceType) {
            self::ENTRY_FACTURA_EMAIL => $comanda->facturaEmails()->find($entryId),
            self::ENTRY_OFERTA_EMAIL => $comanda->ofertaEmails()->find($entryId),
            self::ENTRY_LOG => $comanda->emailLogs()->find($entryId),
            default => null,
        };
    }

    public static function findAttachmentByKey(
        Comanda $comanda,
        string $sourceType,
        int $entryId,
        array $meta = [],
        array $context = [],
        string $attachmentKey = ''
    ): ?array {
        return collect(self::buildHistoryAttachments($comanda, $sourceType, $entryId, $meta, $context))
            ->firstWhere('key', $attachmentKey);
    }

    public static function buildAttachmentResponse(Comanda $comanda, array $attachment, bool $download = false)
    {
        $snapshotPath = trim((string) ($attachment['snapshot_path'] ?? ''));
        $disk = Storage::disk('public');

        if ($snapshotPath !== '' && $disk->exists($snapshotPath)) {
            $fileName = trim((string) ($attachment['original_name'] ?? 'fisier'));

            return $download
                ? $disk->download($snapshotPath, $fileName)
                : $disk->response($snapshotPath, $fileName, [], 'inline');
        }

        return match ((string) ($attachment['kind'] ?? '')) {
            self::KIND_FACTURA => self::buildStoredModelResponse(ComandaFactura::class, $comanda, $attachment, $download),
            self::KIND_MOCKUP => self::buildStoredModelResponse(Mockup::class, $comanda, $attachment, $download),
            self::KIND_ATASAMENT => self::buildStoredModelResponse(ComandaAtasament::class, $comanda, $attachment, $download),
            self::KIND_OFERTA => self::buildOfertaResponse($comanda, $attachment, $download),
            self::KIND_GDPR => self::buildGdprResponse($comanda, $attachment, $download),
            default => abort(Response::HTTP_NOT_FOUND),
        };
    }

    public static function rawHistoryAttachments(array $meta = [], array $context = []): array
    {
        $attachments = collect();

        $metaAttachments = $meta['attachments'] ?? [];
        if (is_array($metaAttachments)) {
            foreach ($metaAttachments as $attachment) {
                if (!is_array($attachment)) {
                    continue;
                }

                $attachments->push(self::normalizeAttachment($attachment));
            }
        }

        $attachments = $attachments->merge(self::legacyAttachments($meta, $context));

        return self::dedupeAttachments($attachments)
            ->values()
            ->all();
    }

    private static function buildSnapshotPayload(
        string $snapshotPath,
        string $kind,
        string $originalName,
        ?string $mime,
        ?int $size,
        array $extra = []
    ): array {
        return array_merge([
            'key' => (string) Str::uuid(),
            'kind' => $kind,
            'label' => trim((string) ($extra['label'] ?? self::defaultLabel($kind))) ?: self::defaultLabel($kind),
            'original_name' => $originalName,
            'snapshot_path' => $snapshotPath,
            'mime' => $mime,
            'size' => $size,
            'source_id' => isset($extra['source_id']) ? (int) $extra['source_id'] : null,
        ], collect($extra)->except(['label', 'source_id'])->all());
    }

    private static function snapshotPath(Comanda $comanda, string $entrySource, string $originalName): string
    {
        $extension = trim((string) pathinfo($originalName, PATHINFO_EXTENSION), '.');
        $baseName = Str::slug((string) pathinfo($originalName, PATHINFO_FILENAME));
        $baseName = $baseName !== '' ? Str::limit($baseName, 60, '') : 'fisier';
        $fileName = now()->format('YmdHis') . '-' . Str::lower(Str::random(10)) . '-' . $baseName;

        if ($extension !== '') {
            $fileName .= '.' . Str::lower($extension);
        }

        return 'comenzi/' . $comanda->id . '/email-snapshots/' . $entrySource . '/' . $fileName;
    }

    private static function safeFileSize($disk, string $path): ?int
    {
        try {
            return (int) $disk->size($path);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function normalizeAttachment(array $attachment): array
    {
        $normalized = [
            'key' => trim((string) ($attachment['key'] ?? '')),
            'kind' => trim((string) ($attachment['kind'] ?? '')),
            'label' => trim((string) ($attachment['label'] ?? '')) ?: self::defaultLabel((string) ($attachment['kind'] ?? '')),
            'original_name' => trim((string) ($attachment['original_name'] ?? '')) ?: 'Fisier',
            'snapshot_path' => trim((string) ($attachment['snapshot_path'] ?? '')) ?: null,
            'mime' => $attachment['mime'] ?? null,
            'size' => isset($attachment['size']) ? (int) $attachment['size'] : null,
            'source_id' => isset($attachment['source_id']) ? (int) $attachment['source_id'] : null,
            'type_label' => isset($attachment['type_label']) ? trim((string) $attachment['type_label']) : null,
        ];

        if ($normalized['key'] === '') {
            $normalized['key'] = self::attachmentKey($normalized);
        }

        return $normalized;
    }

    private static function legacyAttachments(array $meta, array $context): Collection
    {
        $attachments = collect();

        $facturi = $context['facturi'] ?? ($meta['facturi'] ?? []);
        if (is_array($facturi)) {
            foreach ($facturi as $factura) {
                if (!is_array($factura)) {
                    continue;
                }

                $attachments->push(self::normalizeAttachment([
                    'kind' => self::KIND_FACTURA,
                    'label' => 'Factura',
                    'original_name' => (string) ($factura['original_name'] ?? 'Factura'),
                    'mime' => $factura['mime'] ?? null,
                    'size' => $factura['size'] ?? null,
                    'source_id' => $factura['id'] ?? null,
                ]));
            }
        }

        $infoLinks = $meta['info_links'] ?? [];
        if (is_array($infoLinks)) {
            foreach ($infoLinks as $infoLink) {
                if (!is_array($infoLink)) {
                    continue;
                }

                $typeLabel = trim((string) ($infoLink['type_label'] ?? 'Info'));
                $attachments->push(self::normalizeAttachment([
                    'kind' => self::KIND_MOCKUP,
                    'label' => $typeLabel,
                    'original_name' => (string) ($infoLink['original_name'] ?? ($typeLabel !== '' ? $typeLabel : 'Info')),
                    'source_id' => $infoLink['id'] ?? null,
                    'type_label' => $typeLabel,
                ]));
            }
        }

        $documents = collect($meta['documents'] ?? [])
            ->map(fn ($value) => (string) $value)
            ->filter();
        $document = trim((string) ($meta['document'] ?? ''));
        if ($document !== '' && $document !== 'none' && $documents->doesntContain($document)) {
            $documents->push($document);
        }

        foreach ($documents->unique() as $documentType) {
            if ($documentType === self::KIND_OFERTA) {
                $attachments->push(self::normalizeAttachment([
                    'kind' => self::KIND_OFERTA,
                    'label' => 'Oferta',
                    'original_name' => (string) ($context['pdf_name'] ?? 'oferta.pdf'),
                ]));
            }

            if ($documentType === self::KIND_GDPR) {
                $attachments->push(self::normalizeAttachment([
                    'kind' => self::KIND_GDPR,
                    'label' => 'GDPR',
                    'original_name' => (string) ($context['gdpr_name'] ?? 'gdpr.pdf'),
                ]));
            }
        }

        $pdfName = trim((string) ($context['pdf_name'] ?? ''));
        if ($pdfName !== '') {
            $attachments->push(self::normalizeAttachment([
                'kind' => self::KIND_OFERTA,
                'label' => 'Oferta',
                'original_name' => $pdfName,
            ]));
        }

        return $attachments;
    }

    private static function dedupeAttachments(Collection $attachments): Collection
    {
        return $attachments
            ->filter(fn ($attachment) => is_array($attachment) && ($attachment['kind'] ?? '') !== '')
            ->reduce(function (Collection $carry, array $attachment) {
                $existingIndex = $carry->search(function (array $existing) use ($attachment) {
                    return (string) ($existing['kind'] ?? '') === (string) ($attachment['kind'] ?? '')
                        && (int) ($existing['source_id'] ?? 0) === (int) ($attachment['source_id'] ?? 0)
                        && (string) ($existing['original_name'] ?? '') === (string) ($attachment['original_name'] ?? '');
                });

                if ($existingIndex === false) {
                    $carry->push($attachment);
                    return $carry;
                }

                $existing = $carry->get($existingIndex);
                $existingHasSnapshot = !empty($existing['snapshot_path']);
                $attachmentHasSnapshot = !empty($attachment['snapshot_path']);

                if (!$existingHasSnapshot && $attachmentHasSnapshot) {
                    $carry->put($existingIndex, $attachment);
                }

                return $carry;
            }, collect())
            ->values();
    }

    private static function attachmentKey(array $attachment): string
    {
        return substr(sha1(json_encode([
            'kind' => $attachment['kind'] ?? null,
            'snapshot_path' => $attachment['snapshot_path'] ?? null,
            'source_id' => $attachment['source_id'] ?? null,
            'original_name' => $attachment['original_name'] ?? null,
        ])), 0, 16);
    }

    private static function defaultLabel(string $kind): string
    {
        return match ($kind) {
            self::KIND_FACTURA => 'Factura',
            self::KIND_GDPR => 'GDPR',
            self::KIND_MOCKUP => 'Info',
            self::KIND_OFERTA => 'Oferta',
            self::KIND_ATASAMENT => 'Atasament',
            default => 'Fisier',
        };
    }

    private static function attachmentIsAvailable(Comanda $comanda, array $attachment): bool
    {
        $snapshotPath = trim((string) ($attachment['snapshot_path'] ?? ''));
        $disk = Storage::disk('public');

        if ($snapshotPath !== '' && $disk->exists($snapshotPath)) {
            return true;
        }

        return match ((string) ($attachment['kind'] ?? '')) {
            self::KIND_FACTURA => self::storedModelExists(ComandaFactura::class, $comanda, $attachment),
            self::KIND_MOCKUP => self::storedModelExists(Mockup::class, $comanda, $attachment),
            self::KIND_ATASAMENT => self::storedModelExists(ComandaAtasament::class, $comanda, $attachment),
            self::KIND_OFERTA => true,
            self::KIND_GDPR => self::resolveLatestGdprConsent($comanda) instanceof ComandaGdprConsent,
            default => false,
        };
    }

    private static function storedModelExists(string $modelClass, Comanda $comanda, array $attachment): bool
    {
        $sourceId = (int) ($attachment['source_id'] ?? 0);
        if ($sourceId <= 0) {
            return false;
        }

        $model = $modelClass::query()
            ->where('comanda_id', $comanda->id)
            ->find($sourceId);

        if (!$model) {
            return false;
        }

        $path = (string) ($model->path ?? '');
        return $path !== '' && Storage::disk('public')->exists($path);
    }

    private static function buildStoredModelResponse(string $modelClass, Comanda $comanda, array $attachment, bool $download)
    {
        $sourceId = (int) ($attachment['source_id'] ?? 0);
        abort_if($sourceId <= 0, Response::HTTP_NOT_FOUND);

        $model = $modelClass::query()
            ->where('comanda_id', $comanda->id)
            ->findOrFail($sourceId);

        $path = (string) ($model->path ?? '');
        abort_if($path === '', Response::HTTP_NOT_FOUND);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), Response::HTTP_NOT_FOUND);

        $fileName = trim((string) ($attachment['original_name'] ?? $model->original_name ?? 'fisier'));

        return $download
            ? $disk->download($path, $fileName)
            : $disk->response($path, $fileName, [], 'inline');
    }

    private static function buildOfertaResponse(Comanda $comanda, array $attachment, bool $download)
    {
        $fileName = trim((string) ($attachment['original_name'] ?? '')) ?: ComandaPdfFactory::ofertaFilename($comanda);
        $pdf = ComandaPdfFactory::oferta($comanda);

        return $download ? $pdf->download($fileName) : $pdf->stream($fileName);
    }

    private static function buildGdprResponse(Comanda $comanda, array $attachment, bool $download)
    {
        $consent = self::resolveLatestGdprConsent($comanda);
        abort_unless($consent instanceof ComandaGdprConsent, Response::HTTP_NOT_FOUND);

        $fileName = trim((string) ($attachment['original_name'] ?? '')) ?: ComandaPdfFactory::gdprFilename($comanda);
        $pdf = ComandaPdfFactory::gdpr($comanda, $consent);

        return $download ? $pdf->download($fileName) : $pdf->stream($fileName);
    }

    private static function resolveLatestGdprConsent(Comanda $comanda): ?ComandaGdprConsent
    {
        if ($comanda->relationLoaded('gdprConsents')) {
            return $comanda->gdprConsents->sortByDesc('signed_at')->first();
        }

        return $comanda->gdprConsents()->latest('signed_at')->first();
    }
}
