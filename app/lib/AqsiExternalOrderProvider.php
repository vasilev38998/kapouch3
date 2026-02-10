<?php

declare(strict_types=1);

namespace App\Lib;

class AqsiExternalOrderProvider implements ExternalOrderProviderInterface {
    public function fetchOrderByExternalId(string $externalId): ?array {
        $baseUrl = rtrim((string)config('aqsi.base_url', ''), '/');
        $token = (string)config('aqsi.api_token', '');
        if ($baseUrl === '' || $token === '' || trim($externalId) === '') {
            return null;
        }

        $url = $baseUrl . '/v1/orders/' . rawurlencode(trim($externalId));
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

        $total = (float)($json['total'] ?? $json['amount'] ?? 0);
        if ($total <= 0) {
            return null;
        }

        $paidAt = (string)($json['paid_at'] ?? $json['closed_at'] ?? $json['created_at'] ?? '');

        return [
            'external_id' => trim($externalId),
            'total_amount' => round($total, 2),
            'paid_at' => $paidAt,
            'raw' => $json,
        ];
    }
}
