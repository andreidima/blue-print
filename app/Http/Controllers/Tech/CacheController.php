<?php

namespace App\Http\Controllers\Tech;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class CacheController extends Controller
{
    public function index(): View
    {
        return view('tech.cache.index');
    }

    public function clear(Request $request): RedirectResponse
    {
        $request->validate([
            'confirm_clear' => ['accepted'],
        ], [
            'confirm_clear.accepted' => 'Bifeaza confirmarea pentru a continua.',
        ]);

        try {
            Artisan::call('optimize:clear');
            $output = trim((string) Artisan::output());

            Log::info('optimize:clear executed from tech section.', [
                'user_id' => $request->user()?->id,
                'user_email' => $request->user()?->email,
                'ip' => $request->ip(),
            ]);

            return redirect()
                ->route('tech.cache.index')
                ->with('cacheClearStatus', [
                    'type' => 'success',
                    'message' => 'Cache-ul aplicatiei a fost curatat.',
                    'output' => $output !== '' ? $output : 'Comanda s-a executat fara output.',
                ]);
        } catch (Throwable $exception) {
            Log::error('optimize:clear failed from tech section.', [
                'user_id' => $request->user()?->id,
                'user_email' => $request->user()?->email,
                'ip' => $request->ip(),
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('tech.cache.index')
                ->with('cacheClearStatus', [
                    'type' => 'danger',
                    'message' => 'Curatarea cache-ului a esuat: ' . $exception->getMessage(),
                ]);
        }
    }
}
