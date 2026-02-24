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
];

