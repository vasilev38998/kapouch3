<?php

declare(strict_types=1);

namespace App\Lib;

class SmsRuClient {
    public function sendOtp(string $phone, string $otp): array {
        $params = [
            'api_id' => config('sms_ru.api_id'),
            'to' => $phone,
            'msg' => 'Код входа: ' . $otp,
            'json' => 1,
            'test' => config('sms_ru.test') ? 1 : 0,
            'from' => config('sms_ru.from'),
        ];

        $ch = curl_init('https://sms.ru/sms/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            app_log('sms.ru error: ' . $error);
            return ['ok' => false, 'message_id' => null, 'error' => $error];
        }
        curl_close($ch);
        $json = json_decode($response, true);
        $status = ($json['status'] ?? 'ERROR') === 'OK';
        $messageId = $json['sms'][$phone]['sms_id'] ?? null;
        $error = $status ? null : ($json['status_text'] ?? 'Unknown sms error');
        if (!$status) app_log('sms.ru send failed: ' . $response);
        return ['ok' => $status, 'message_id' => $messageId, 'error' => $error];
    }
}
