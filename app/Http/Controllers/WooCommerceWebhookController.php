<?php

namespace App\Http\Controllers;

use App\Enums\MetodaPlata;
use App\Enums\SursaComanda;
use App\Enums\StatusComanda;
use App\Enums\TipComanda;
use App\Models\Client;
use App\Models\Comanda;
use App\Models\ComandaAtasament;
use App\Models\ComandaProdus;
use App\Models\Plata;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class WooCommerceWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret = config('services.woocommerce.webhook_secret');

        if (!$secret) {
            Log::error('WooCommerce webhook secret missing.');
            return response()->json(['message' => 'Webhook not configured'], 500);
        }

        $payload = $request->getContent();
        $signature = $request->header('X-WC-Webhook-Signature');

        if (!$this->isValidSignature($payload, $signature, $secret)) {
            Log::warning('WooCommerce webhook signature invalid.', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $orderId = trim((string) data_get($data, 'id'));
        if ($orderId === '') {
            return response()->json(['message' => 'Missing order id'], 422);
        }

        try {
            $comanda = $this->syncOrder($data);
        } catch (Throwable $e) {
            Log::error('WooCommerce order sync failed.', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return response()->json(['message' => 'Order sync failed'], 500);
        }

        return response()->json([
            'message' => 'OK',
            'comanda_id' => $comanda->id,
        ]);
    }

    private function isValidSignature(string $payload, ?string $signature, string $secret): bool
    {
        if (!$signature) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        return hash_equals($expected, $signature);
    }

    private function syncOrder(array $payload): Comanda
    {
        $orderId = trim((string) data_get($payload, 'id'));

        return DB::transaction(function () use ($payload, $orderId) {
            $comanda = Comanda::where('woocommerce_order_id', $orderId)->first();
            $client = $this->resolveClient($payload, $comanda?->client);

            if (!$comanda) {
                $comanda = Comanda::create([
                    'woocommerce_order_id' => $orderId,
                    'client_id' => $client->id,
                    'tip' => TipComanda::ComandaFerma->value,
                    'sursa' => SursaComanda::Website->value,
                    'status' => StatusComanda::Nou->value,
                    'data_solicitarii' => $this->resolveDataSolicitarii($payload),
                    'timp_estimat_livrare' => $this->resolveTimpLivrare($payload),
                    'necesita_tipar_exemplu' => false,
                    'necesita_mockup' => false,
                    'solicitare_client' => $this->resolveSolicitareClient($payload),
                    'cantitate' => $this->resolveCantitate($payload),
                ]);

                $this->storeLineItems($comanda, $payload);
            } else {
                $updates = [];

                if ($client->id !== $comanda->client_id) {
                    $updates['client_id'] = $client->id;
                }

                if (!$comanda->solicitare_client) {
                    $note = $this->resolveSolicitareClient($payload);
                    if ($note) {
                        $updates['solicitare_client'] = $note;
                    }
                }

                if (!$comanda->timp_estimat_livrare) {
                    $updates['timp_estimat_livrare'] = $this->resolveTimpLivrare($payload);
                }

                if (!empty($updates)) {
                    $comanda->update($updates);
                }

                if ($comanda->produse()->count() === 0) {
                    $this->storeLineItems($comanda, $payload);
                }
            }

            $this->storeAttachments($comanda, $payload);
            $this->storePayment($comanda, $payload);
            $comanda->recalculateTotals();

            return $comanda;
        });
    }

    private function resolveClient(array $payload, ?Client $existing = null): Client
    {
        $billing = (array) data_get($payload, 'billing', []);
        $shipping = (array) data_get($payload, 'shipping', []);

        $email = trim((string) data_get($billing, 'email'));
        $phone = trim((string) data_get($billing, 'phone'));
        $name = $this->buildClientName($billing, $shipping);
        $address = $this->buildAddress($shipping, $billing);
        $type = $this->resolveClientType($billing);

        $client = $existing;

        if (!$client && $email !== '') {
            $client = Client::where('email', $email)->first();
        }

        if (!$client && $phone !== '') {
            $client = Client::where('telefon', $phone)->first();
        }

        if (!$client) {
            return Client::create([
                'type' => $type,
                'nume' => $name !== '' ? $name : 'Client website',
                'adresa' => $address ?: null,
                'telefon' => $phone ?: null,
                'email' => $email ?: null,
            ]);
        }

        $updates = [];

        if ($type === 'pj' && $client->type !== 'pj') {
            $updates['type'] = 'pj';
        }

        if (!$client->adresa && $address) {
            $updates['adresa'] = $address;
        }

        if (!$client->telefon && $phone) {
            $updates['telefon'] = $phone;
        }

        if (!$client->email && $email) {
            $updates['email'] = $email;
        }

        if (!empty($updates)) {
            $client->update($updates);
        }

        return $client;
    }

    private function buildClientName(array $billing, array $shipping): string
    {
        $company = trim((string) data_get($billing, 'company'));
        if ($company !== '') {
            return $company;
        }

        $first = trim((string) data_get($billing, 'first_name'));
        $last = trim((string) data_get($billing, 'last_name'));
        $name = trim($first . ' ' . $last);
        if ($name !== '') {
            return $name;
        }

        $shipFirst = trim((string) data_get($shipping, 'first_name'));
        $shipLast = trim((string) data_get($shipping, 'last_name'));

        return trim($shipFirst . ' ' . $shipLast);
    }

    private function buildAddress(array $shipping, array $billing): ?string
    {
        $source = $this->hasAddress($shipping) ? $shipping : $billing;

        $parts = [
            trim((string) data_get($source, 'address_1')),
            trim((string) data_get($source, 'address_2')),
            trim((string) data_get($source, 'city')),
            trim((string) data_get($source, 'state')),
            trim((string) data_get($source, 'postcode')),
            trim((string) data_get($source, 'country')),
        ];

        $parts = array_filter($parts, fn ($value) => $value !== '');
        if (empty($parts)) {
            return null;
        }

        return implode(', ', $parts);
    }

    private function hasAddress(array $address): bool
    {
        return trim((string) data_get($address, 'address_1')) !== ''
            || trim((string) data_get($address, 'address_2')) !== ''
            || trim((string) data_get($address, 'city')) !== '';
    }

    private function resolveClientType(array $billing): string
    {
        $company = trim((string) data_get($billing, 'company'));

        return $company !== '' ? 'pj' : 'pf';
    }

    private function resolveDataSolicitarii(array $payload): string
    {
        $date = $this->parseDateTime(data_get($payload, 'date_created'))
            ?? $this->parseDateTime(data_get($payload, 'date_created_gmt'))
            ?? now();

        return $date->toDateString();
    }

    private function resolveTimpLivrare(array $payload): Carbon
    {
        $date = $this->parseDateTime(data_get($payload, 'date_created'))
            ?? $this->parseDateTime(data_get($payload, 'date_created_gmt'))
            ?? now();

        return $date->copy()->addDay();
    }

    private function resolveSolicitareClient(array $payload): ?string
    {
        $note = trim((string) data_get($payload, 'customer_note'));

        return $note !== '' ? $note : null;
    }

    private function resolveCantitate(array $payload): ?int
    {
        $items = (array) data_get($payload, 'line_items', []);
        $total = 0;

        foreach ($items as $item) {
            $total += (int) data_get($item, 'quantity', 0);
        }

        return $total > 0 ? $total : null;
    }

    private function storeLineItems(Comanda $comanda, array $payload): void
    {
        $lines = $this->buildOrderLines($payload);

        foreach ($lines as $line) {
            ComandaProdus::create([
                'comanda_id' => $comanda->id,
                'produs_id' => null,
                'custom_denumire' => $line['name'],
                'cantitate' => $line['quantity'],
                'pret_unitar' => $line['unit_price'],
                'total_linie' => $line['total'],
            ]);
        }
    }

    private function buildOrderLines(array $payload): array
    {
        $lines = [];

        $lineItems = (array) data_get($payload, 'line_items', []);
        foreach ($lineItems as $item) {
            $name = trim((string) data_get($item, 'name')) ?: 'Produs';
            $quantity = (int) data_get($item, 'quantity', 1);
            $lineTotal = $this->toFloat(data_get($item, 'total', 0));
            if ($lineTotal == 0.0) {
                $lineTotal = $this->toFloat(data_get($item, 'subtotal', 0));
            }

            if ($quantity <= 0) {
                $quantity = 1;
            }

            $unitPrice = $quantity > 0 ? $lineTotal / $quantity : $lineTotal;

            $lines[] = $this->makeLine($name, $quantity, $unitPrice, $lineTotal);
        }

        $shippingLines = (array) data_get($payload, 'shipping_lines', []);
        foreach ($shippingLines as $shipping) {
            $total = $this->toFloat(data_get($shipping, 'total', 0));
            if ($total == 0.0) {
                continue;
            }

            $method = trim((string) data_get($shipping, 'method_title'));
            $name = $method !== '' ? "Transport: {$method}" : 'Transport';
            $lines[] = $this->makeLine($name, 1, $total, $total);
        }

        $feeLines = (array) data_get($payload, 'fee_lines', []);
        foreach ($feeLines as $fee) {
            $total = $this->toFloat(data_get($fee, 'total', 0));
            if ($total == 0.0) {
                continue;
            }

            $name = trim((string) data_get($fee, 'name')) ?: 'Taxa';
            $lines[] = $this->makeLine($name, 1, $total, $total);
        }

        $couponLines = (array) data_get($payload, 'coupon_lines', []);
        foreach ($couponLines as $coupon) {
            $discount = $this->toFloat(data_get($coupon, 'discount', 0));
            if ($discount == 0.0) {
                continue;
            }

            $code = trim((string) data_get($coupon, 'code'));
            $name = $code !== '' ? "Discount: {$code}" : 'Discount';
            $value = -abs($discount);
            $lines[] = $this->makeLine($name, 1, $value, $value);
        }

        $taxTotal = $this->toFloat(data_get($payload, 'total_tax', 0))
            + $this->toFloat(data_get($payload, 'shipping_tax', 0));
        if ($taxTotal != 0.0) {
            $lines[] = $this->makeLine('Taxe', 1, $taxTotal, $taxTotal);
        }

        $orderTotal = $this->toFloat(data_get($payload, 'total', 0));
        $computedTotal = array_sum(array_map(fn ($line) => $line['total'], $lines));
        $diff = round($orderTotal - $computedTotal, 2);

        if (abs($diff) >= 0.01) {
            $lines[] = $this->makeLine('Ajustare total WooCommerce', 1, $diff, $diff);
        }

        return $lines;
    }

    private function makeLine(string $name, int $quantity, float $unitPrice, float $total): array
    {
        if ($quantity <= 0) {
            $quantity = 1;
        }

        return [
            'name' => $name,
            'quantity' => $quantity,
            'unit_price' => round($unitPrice, 2),
            'total' => round($total, 2),
        ];
    }

    private function storeAttachments(Comanda $comanda, array $payload): void
    {
        $attachments = $this->extractAttachments($payload);
        if (empty($attachments)) {
            return;
        }

        $disk = Storage::disk('public');

        foreach ($attachments as $attachment) {
            $url = $attachment['url'] ?? null;
            if (!$url) {
                continue;
            }

            $originalName = $this->resolveAttachmentName($attachment);
            if ($originalName && $comanda->atasamente()->where('original_name', $originalName)->exists()) {
                continue;
            }

            $response = Http::timeout(20)
                ->withOptions(['allow_redirects' => true])
                ->get($url);

            if (!$response->ok()) {
                Log::warning('WooCommerce attachment download failed.', [
                    'comanda_id' => $comanda->id,
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                continue;
            }

            $content = $response->body();
            if ($content === '') {
                continue;
            }

            $mime = $attachment['mime'] ?? $response->header('Content-Type') ?? 'application/octet-stream';
            $extension = $this->resolveAttachmentExtension($originalName, $url);
            $fileName = (string) Str::uuid();
            if ($extension !== '') {
                $fileName .= '.' . $extension;
            }

            $path = 'comenzi/' . $comanda->id . '/atasamente/' . $fileName;
            $disk->put($path, $content);

            ComandaAtasament::create([
                'comanda_id' => $comanda->id,
                'uploaded_by' => null,
                'original_name' => $originalName ?: $fileName,
                'path' => $path,
                'mime' => $mime,
                'size' => strlen($content),
            ]);
        }
    }

    private function resolveAttachmentName(array $attachment): ?string
    {
        $name = $attachment['name'] ?? null;
        if (is_string($name) && trim($name) !== '') {
            return trim($name);
        }

        $url = $attachment['url'] ?? null;
        if (is_string($url)) {
            $path = (string) parse_url($url, PHP_URL_PATH);
            $basename = trim(basename($path));
            if ($basename !== '') {
                return $basename;
            }
        }

        return null;
    }

    private function resolveAttachmentExtension(?string $name, string $url): string
    {
        if ($name) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            if ($ext !== '') {
                return $ext;
            }
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    private function extractAttachments(array $payload): array
    {
        $attachments = [];

        $payloadAttachments = data_get($payload, 'attachments');
        if (is_array($payloadAttachments)) {
            $attachments = array_merge($attachments, $this->normalizeAttachmentValue($payloadAttachments));
        }

        $metaKeys = config('services.woocommerce.attachment_meta_keys', []);
        if (!empty($metaKeys)) {
            $metaData = (array) data_get($payload, 'meta_data', []);
            foreach ($metaData as $meta) {
                $key = data_get($meta, 'key');
                if (!$key || !in_array($key, $metaKeys, true)) {
                    continue;
                }

                $attachments = array_merge(
                    $attachments,
                    $this->normalizeAttachmentValue(data_get($meta, 'value'))
                );
            }
        }

        return $this->uniqueAttachments($attachments);
    }

    private function normalizeAttachmentValue(mixed $value): array
    {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return [];
            }

            if ($this->looksLikeJson($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $this->normalizeAttachmentValue($decoded);
                }
            }

            return $this->looksLikeUrl($value)
                ? [['url' => $value, 'name' => null, 'mime' => null]]
                : [];
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                $items = [];
                foreach ($value as $entry) {
                    $items = array_merge($items, $this->normalizeAttachmentValue($entry));
                }
                return $items;
            }

            $url = $value['url'] ?? $value['link'] ?? $value['file'] ?? null;
            $name = $value['name'] ?? $value['filename'] ?? null;
            $mime = $value['mime'] ?? $value['type'] ?? null;

            if (is_string($url) && $this->looksLikeUrl($url)) {
                return [[
                    'url' => $url,
                    'name' => is_string($name) ? trim($name) : null,
                    'mime' => is_string($mime) ? trim($mime) : null,
                ]];
            }
        }

        return [];
    }

    private function uniqueAttachments(array $attachments): array
    {
        $unique = [];
        $seen = [];

        foreach ($attachments as $attachment) {
            $url = $attachment['url'] ?? '';
            $name = $attachment['name'] ?? '';
            $key = strtolower((string) ($url ?: $name));

            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $attachment;
        }

        return $unique;
    }

    private function looksLikeUrl(string $value): bool
    {
        return preg_match('/^https?:\\/\\//i', $value) === 1;
    }

    private function looksLikeJson(string $value): bool
    {
        $value = ltrim($value);

        return str_starts_with($value, '{') || str_starts_with($value, '[');
    }

    private function storePayment(Comanda $comanda, array $payload): void
    {
        $total = $this->toFloat(data_get($payload, 'total', 0));
        if ($total <= 0) {
            return;
        }

        $paidAt = $this->parseDateTime(data_get($payload, 'date_paid'))
            ?? $this->parseDateTime(data_get($payload, 'date_paid_gmt'));
        $status = strtolower((string) data_get($payload, 'status'));

        $isPaid = $paidAt !== null || in_array($status, ['processing', 'completed'], true);
        if (!$isPaid) {
            return;
        }

        $transactionId = $this->resolveTransactionId($payload);
        if ($transactionId !== null) {
            $exists = $comanda->plati()->where('note', $transactionId)->exists();
            if ($exists) {
                return;
            }
        } elseif ($comanda->plati()->where('suma', $total)->exists()) {
            return;
        }

        $metoda = $this->resolveMetodaPlata($payload);

        Plata::create([
            'comanda_id' => $comanda->id,
            'suma' => $total,
            'metoda' => $metoda,
            'numar_factura' => null,
            'platit_la' => $paidAt ?? now(),
            'note' => $transactionId,
            'created_by' => null,
        ]);
    }

    private function resolveTransactionId(array $payload): ?string
    {
        $transactionId = trim((string) data_get($payload, 'transaction_id'));
        if ($transactionId !== '') {
            return $transactionId;
        }

        $metaKeys = config('services.woocommerce.transaction_meta_keys', []);
        if (empty($metaKeys)) {
            return null;
        }

        $metaValue = $this->getMetaValue($payload, $metaKeys);
        $metaValue = is_string($metaValue) ? trim($metaValue) : '';

        return $metaValue !== '' ? $metaValue : null;
    }

    private function getMetaValue(array $payload, array $keys): mixed
    {
        $metaData = (array) data_get($payload, 'meta_data', []);
        foreach ($metaData as $meta) {
            $key = data_get($meta, 'key');
            if (!$key || !in_array($key, $keys, true)) {
                continue;
            }

            return data_get($meta, 'value');
        }

        return null;
    }

    private function resolveMetodaPlata(array $payload): string
    {
        $method = strtolower((string) data_get($payload, 'payment_method'));

        return match ($method) {
            'cod', 'cash' => MetodaPlata::Cash->value,
            'bacs', 'bank_transfer' => MetodaPlata::Transfer->value,
            default => MetodaPlata::Card->value,
        };
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function toFloat(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            return (float) str_replace(',', '.', $value);
        }

        return 0.0;
    }
}
