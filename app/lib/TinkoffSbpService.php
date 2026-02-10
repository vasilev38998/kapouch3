<?php

declare(strict_types=1);

namespace App\Lib;

class TinkoffSbpService {
    public function createSbpPayment(array $payload): array {
        $terminalKey = (string)config('tinkoff.terminal_key', '');
        $password = (string)config('tinkoff.password', '');
        $baseUrl = rtrim((string)config('tinkoff.base_url', 'https://securepay.tinkoff.ru/v2'), '/');

        if ($terminalKey === '' || $password === '') {
            return ['ok' => false, 'error' => 'config_missing'];
        }

        $request = [
            'TerminalKey' => $terminalKey,
            'Amount' => (int)$payload['amount_kopecks'],
            'OrderId' => (string)$payload['order_id'],
            'Description' => (string)($payload['description'] ?? 'Оплата заказа Kapouch'),
            'PayType' => 'O',
            'NotificationURL' => (string)($payload['notification_url'] ?? ''),
            'SuccessURL' => (string)($payload['success_url'] ?? ''),
            'FailURL' => (string)($payload['fail_url'] ?? ''),
        ];

        if (!empty($payload['customer_key'])) {
            $request['CustomerKey'] = (string)$payload['customer_key'];
        }

        $request['Token'] = $this->sign($request, $password);

        $ch = curl_init($baseUrl . '/Init');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($request, JSON_UNESCAPED_UNICODE),
        ]);

        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300 || !is_string($body) || $body === '') {
            return ['ok' => false, 'error' => 'http_error'];
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'bad_json'];
        }

        if (empty($json['Success'])) {
            return [
                'ok' => false,
                'error' => 'provider_error',
                'details' => (string)($json['Message'] ?? $json['Details'] ?? ''),
                'raw' => $json,
            ];
        }

        $paymentUrl = (string)($json['PaymentURL'] ?? '');
        if ($paymentUrl === '') {
            return ['ok' => false, 'error' => 'empty_payment_url', 'raw' => $json];
        }

        return [
            'ok' => true,
            'payment_id' => (string)($json['PaymentId'] ?? ''),
            'payment_url' => $paymentUrl,
            'raw' => $json,
        ];
    }

    private function sign(array $request, string $password): string {
        $data = $request;
        unset($data['Token']);
        $data['Password'] = $password;

        $flat = [];
        foreach ($data as $k => $v) {
            if (is_scalar($v)) {
                $flat[$k] = (string)$v;
            }
        }
        ksort($flat);

        return hash('sha256', implode('', $flat));
    }
}
