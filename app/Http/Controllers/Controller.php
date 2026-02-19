<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function rememberReturnUrl(Request $request, ?string $fallbackRoute = null): void
    {
        $session = $request->session();
        $currentUrl = (string) $request->fullUrl();
        $previousUrl = (string) url()->previous();
        $candidate = null;

        if ($previousUrl !== '') {
            $currentHost = strtolower((string) $request->getHost());
            $previousHost = strtolower((string) (parse_url($previousUrl, PHP_URL_HOST) ?? ''));
            $sameHost = $previousHost === '' || $previousHost === $currentHost;
            $normalizedCurrent = rtrim($currentUrl, '/');
            $normalizedPrevious = rtrim($previousUrl, '/');
            $isSameUrl = $normalizedCurrent !== '' && $normalizedCurrent === $normalizedPrevious;
            $previousPath = trim((string) (parse_url($previousUrl, PHP_URL_PATH) ?? ''), '/');
            $isAuthBoundary = in_array($previousPath, ['login', 'logout'], true);
            $isAppUrl = Str::startsWith($previousUrl, ['http://', 'https://']);

            if ($sameHost && !$isSameUrl && !$isAuthBoundary && $isAppUrl) {
                $candidate = $previousUrl;
            }
        }

        if ($candidate !== null) {
            $session->put('returnUrl', $candidate);
            return;
        }

        if (!$session->has('returnUrl') && $fallbackRoute) {
            $session->put('returnUrl', route($fallbackRoute));
        }
    }
}
