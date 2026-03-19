<?php

namespace App\Support;

class ClientEmailSupport
{
    public static function normalize(array $emails): array
    {
        return collect($emails)
            ->flatten()
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function first(array $emails): ?string
    {
        $emails = static::normalize($emails);

        return $emails[0] ?? null;
    }

    public static function format(array $emails): ?string
    {
        $emails = static::normalize($emails);

        return empty($emails) ? null : implode(', ', $emails);
    }

    public static function recipientsFromMeta(?string $recipient, mixed $meta = null): array
    {
        $metaRecipients = is_array($meta) ? ($meta['recipients'] ?? []) : [];

        if (is_array($metaRecipients)) {
            $normalized = static::normalize($metaRecipients);
            if ($normalized !== []) {
                return $normalized;
            }
        }

        return static::normalize([$recipient]);
    }
}
