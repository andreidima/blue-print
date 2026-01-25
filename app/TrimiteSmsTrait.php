<?php

namespace App;

use App\Models\SmsMessage;

trait TrimiteSmsTrait
{
    public function trimiteSms(array $telefoane, string $mesaj, array $meta = []): array
    {
        $mesaj = trim($mesaj);
        $messageSent = $this->normalizeSmsText($mesaj);
        $test = (int) config('sms_link.test', 0);
        $connectionId = config('sms_link.connection_id');
        $password = config('sms_link.password');
        $provider = $meta['provider'] ?? 'smslink';
        $results = [];

        foreach ($telefoane as $telefon) {
            $telefon = trim((string) $telefon);
            if ($telefon === '') {
                continue;
            }

            $sms = new SmsMessage();
            $sms->comanda_id = $meta['comanda_id'] ?? null;
            $sms->sms_template_id = $meta['sms_template_id'] ?? null;
            $sms->sent_by = $meta['sent_by'] ?? null;
            $sms->recipient = $telefon;
            $sms->message = $mesaj;
            $sms->message_sent = $messageSent;
            $sms->provider = $provider;

            $query = http_build_query([
                'connection_id' => $connectionId,
                'password' => $password,
                'to' => $telefon,
                'message' => $messageSent,
                'test' => $test,
            ], '', '&', PHP_QUERY_RFC3986);

            $url = 'https://secure.smslink.ro/sms/gateway/communicate/?' . $query;
            $content = @file_get_contents($url);

            if ($content === false) {
                $error = error_get_last()['message'] ?? 'Eroare necunoscuta';
                $sms->status = 'failed';
                $sms->gateway_message = $error;
                $sms->save();
                $results[] = $sms;
                continue;
            }

            $sms->gateway_raw = $content;

            [$level, $code, $response, $variables] = array_pad(explode(';', $content), 4, null);
            $sms->gateway_level = $level;
            $sms->gateway_code = $code;
            $sms->gateway_message = $response;

            if ($level === 'MESSAGE' && (int) $code === 1) {
                $gatewayVars = $variables ? explode(',', $variables) : [];
                $sms->status = 'sent';
                $sms->gateway_message_id = $gatewayVars[0] ?? null;
                $sms->sent_at = now();
            } else {
                $sms->status = 'failed';
            }

            $sms->save();
            $results[] = $sms;
        }

        return $results;
    }

    private function normalizeSmsText(string $message): string
    {
        $diacriticsMap = [
            'ă' => 'a', 'â' => 'a', 'î' => 'i',
            'ș' => 's', 'ş' => 's',
            'ț' => 't', 'ţ' => 't',
            'Ă' => 'A', 'Â' => 'A', 'Î' => 'I',
            'Ș' => 'S', 'Ş' => 'S',
            'Ț' => 'T', 'Ţ' => 'T',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'ã' => 'a',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Ã' => 'A',
            'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Õ' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
            'ç' => 'c', 'Ç' => 'C', 'ñ' => 'n', 'Ñ' => 'N',
        ];

        $message = strtr($message, $diacriticsMap);

        return preg_replace('/[^\x20-\x7E]/', '?', $message) ?? '';
    }
}
