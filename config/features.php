<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Toggle temporary UX changes without deleting code immediately.
    |
    */

    // TODO(2026-02-19): Temporary hide for invoice email popup zones.
    // If users confirm by 2026-02-26 that the dedicated email page is enough,
    // remove popup UI/JS/backend references completely.
    'order_invoice_email_popup_enabled' => (bool) env('FEATURE_ORDER_INVOICE_EMAIL_POPUP', false),

    // Allow inline PDF preview in local environments.
    'pdf_preview_enabled_in_local' => (bool) env('FEATURE_PDF_PREVIEW_LOCAL', true),

    // Optional allowlist for preview on non-local environments.
    'pdf_preview_user_ids' => array_values(array_filter(array_map(
        static fn (string $value): int => (int) trim($value),
        explode(',', (string) env('FEATURE_PDF_PREVIEW_USER_IDS', ''))
    ))),
    'pdf_preview_user_emails' => array_values(array_filter(array_map(
        static fn (string $value): string => strtolower(trim($value)),
        explode(',', (string) env('FEATURE_PDF_PREVIEW_USER_EMAILS', ''))
    ))),
];
