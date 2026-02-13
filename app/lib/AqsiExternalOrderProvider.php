<?php

declare(strict_types=1);

namespace App\Lib;

class AqsiExternalOrderProvider implements ExternalOrderProviderInterface {
    public function fetchOrderByExternalId(string $externalId): ?array {
        $externalId = trim($externalId);
        if ($externalId === '') {
            return null;
        }

        $receipt = $this->fetchByPath((string)Settings::get('aqsi.receipt_path', config('aqsi.receipt_path', '/v1/receipts/{id}')), $externalId, 'receipt');
        if ($receipt) {
            return $receipt;
        }

        return $this->fetchByPath((string)Settings::get('aqsi.order_path', config('aqsi.order_path', '/v1/orders/{id}')), $externalId, 'order');
    }

    private function fetchByPath(string $pathTemplate, string $externalId, string $source): ?array {
        $baseUrl = rtrim((string)Settings::get('aqsi.base_url', config('aqsi.base_url', '')), '/');
        $token = (string)Settings::get('aqsi.api_token', config('aqsi.api_token', ''));
        if ($baseUrl === '' || $token === '' || $pathTemplate === '') {
            return null;
        }

        $path = str_replace('{id}', rawurlencode($externalId), $pathTemplate);
        $url = $baseUrl . '/' . ltrim($path, '/');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
        ]);

        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300 || !is_string($body) || $body === '') {
            return null;
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            return null;
        }

        $total = $this->extractTotal($json);
        if ($total <= 0) {
            return null;
        }

        $paidAt = (string)($json['paid_at'] ?? $json['closed_at'] ?? $json['created_at'] ?? '');

        return [
            'external_id' => $externalId,
            'total_amount' => round($total, 2),
            'paid_at' => $paidAt,
            'source' => $source,
            'raw' => $json,
        ];
    }

    private function extractTotal(array $json): float {
        $direct = (float)($json['total'] ?? $json['amount'] ?? $json['sum'] ?? 0);
        if ($direct > 0) {
            return $direct;
        }

        $items = $json['positions'] ?? $json['items'] ?? null;
        if (!is_array($items)) {
            return 0;
        }

        $total = 0.0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $price = (float)($item['price'] ?? $item['amount'] ?? 0);
            $qty = (float)($item['quantity'] ?? $item['qty'] ?? 1);
            if ($price <= 0 || $qty <= 0) {
                continue;
            }
            $total += $price * $qty;
        }
        return $total;
    }
}
