<?php

namespace App\Http\Controllers;

use App\Enums\StatusComanda;
use App\Enums\SursaComanda;
use App\Enums\TipComanda;
use App\Models\Comanda;
use App\Models\SmsTemplate;
use App\TrimiteSmsTrait;
use Illuminate\Http\Request;

class ComandaSmsController extends Controller
{
    use TrimiteSmsTrait;

    public function show(Request $request, Comanda $comanda)
    {
        $request->session()->get('returnUrl') ?: $request->session()->put('returnUrl', url()->previous());

        $comanda->load([
            'client',
            'smsMessages' => fn ($query) => $query->latest(),
            'smsMessages.sentBy',
            'smsMessages.template',
        ]);

        $smsTemplates = SmsTemplate::query()
            ->active()
            ->orderBy('name')
            ->get();

        if ($smsTemplates->isEmpty()) {
            $smsTemplates = SmsTemplate::query()
                ->orderBy('name')
                ->get();
        }

        $placeholders = $this->buildPlaceholders($comanda);

        $defaultTemplate = $smsTemplates->firstWhere('key', 'comanda_finalizata') ?? $smsTemplates->first();
        $defaultTemplateId = $defaultTemplate?->id;
        $defaultBody = $defaultTemplate?->body ?? '';
        $defaultMessage = $defaultBody ? $this->replacePlaceholders($defaultBody, $placeholders) : '';

        $clientTelefon = optional($comanda->client)->telefon ?? '';

        return view('comenzi.sms', [
            'comanda' => $comanda,
            'smsTemplates' => $smsTemplates,
            'placeholders' => $placeholders,
            'smsMessages' => $comanda->smsMessages,
            'defaultTemplateId' => $defaultTemplateId,
            'defaultMessage' => $defaultMessage,
            'clientTelefon' => $clientTelefon,
        ]);
    }

    public function send(Request $request, Comanda $comanda)
    {
        $data = $request->validate([
            'template_id' => ['nullable', 'integer', 'exists:sms_templates,id'],
            'recipients' => ['required', 'string'],
            'message' => ['required', 'string'],
        ]);

        $template = null;
        if (!empty($data['template_id'])) {
            $template = SmsTemplate::find($data['template_id']);
        }

        $placeholders = $this->buildPlaceholders($comanda);
        $message = $this->replacePlaceholders($data['message'], $placeholders);
        $recipients = $this->parseRecipients($data['recipients']);

        if (empty($recipients)) {
            return back()
                ->with('warning', 'Nu s-a gasit niciun numar valid.')
                ->withInput();
        }

        $results = $this->trimiteSms($recipients, $message, [
            'comanda_id' => $comanda->id,
            'sms_template_id' => $template?->id,
            'sent_by' => $request->user()?->id,
        ]);

        $sentCount = collect($results)->where('status', 'sent')->count();
        $failedCount = collect($results)->where('status', 'failed')->count();

        if ($sentCount > 0 && $failedCount === 0) {
            $successMessage = $sentCount === 1
                ? 'SMS-ul a fost trimis.'
                : "S-au trimis {$sentCount} SMS-uri.";

            return redirect()
                ->route('comenzi.sms.show', $comanda)
                ->with('success', $successMessage);
        }

        $statusMessage = "S-au trimis {$sentCount} SMS-uri. {$failedCount} au esuat.";

        return redirect()
            ->route('comenzi.sms.show', $comanda)
            ->with('warning', $statusMessage);
    }

    private function parseRecipients(string $input): array
    {
        $chunks = preg_split('/[,\n;]+/', $input) ?: [];
        $recipients = [];

        foreach ($chunks as $chunk) {
            $value = trim($chunk);
            if ($value === '') {
                continue;
            }

            $value = preg_replace('/\s+/', '', $value);
            if ($value === '') {
                continue;
            }

            $recipients[] = $value;
        }

        return array_values(array_unique($recipients));
    }

    private function replacePlaceholders(string $message, array $placeholders): string
    {
        return strtr($message, $placeholders);
    }

    private function buildPlaceholders(Comanda $comanda): array
    {
        $client = $comanda->client;
        $tipuri = TipComanda::options();
        $surse = SursaComanda::options();
        $statusuri = StatusComanda::options();

        return [
            '{app}' => config('app.name'),
            '{comanda_id}' => (string) $comanda->id,
            '{client}' => $client?->nume_complet ?? '',
            '{telefon}' => $client?->telefon ?? '',
            '{email}' => $client?->email ?? '',
            '{total}' => number_format((float) $comanda->total, 2),
            '{livrare}' => optional($comanda->timp_estimat_livrare)->format('d.m.Y H:i') ?? '',
            '{finalizat_la}' => optional($comanda->finalizat_la)->format('d.m.Y H:i') ?? '',
            '{status}' => $statusuri[$comanda->status] ?? $comanda->status,
            '{tip}' => $tipuri[$comanda->tip] ?? $comanda->tip,
            '{sursa}' => $surse[$comanda->sursa] ?? $comanda->sursa,
        ];
    }
}
