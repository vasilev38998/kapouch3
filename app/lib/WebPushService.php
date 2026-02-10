<?php

declare(strict_types=1);

namespace App\Lib;

class WebPushService {
    public function isAvailable(): bool {
        return class_exists(\Minishlink\WebPush\WebPush::class)
            && class_exists(\Minishlink\WebPush\Subscription::class)
            && !empty(config('web_push.public_key'))
            && !empty(config('web_push.private_key'));
    }

    /**
     * @param array<int,array<string,mixed>> $subscriptions
     * @param array<string,mixed> $payload
     * @return array{sent:int,failed:int,errors:array<int,string>}
     */
    public function sendToSubscriptions(array $subscriptions, array $payload): array {
        if (!$this->isAvailable()) {
            return ['sent' => 0, 'failed' => count($subscriptions), 'errors' => ['web-push dependency or VAPID keys are missing']];
        }

        $auth = [
            'VAPID' => [
                'subject' => (string)config('web_push.subject', 'mailto:admin@example.com'),
                'publicKey' => (string)config('web_push.public_key', ''),
                'privateKey' => (string)config('web_push.private_key', ''),
            ],
        ];

        $webPush = new \Minishlink\WebPush\WebPush($auth, ['TTL' => 120]);
        foreach ($subscriptions as $sub) {
            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint' => (string)$sub['endpoint'],
                'publicKey' => (string)($sub['p256dh'] ?? ''),
                'authToken' => (string)($sub['auth'] ?? ''),
                'contentEncoding' => (string)($sub['content_encoding'] ?? 'aesgcm'),
            ]);
            $webPush->queueNotification($subscription, json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        $sent = 0;
        $failed = 0;
        $errors = [];
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
            } else {
                $failed++;
                $errors[] = (string)$report->getReason();
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'errors' => array_values(array_unique($errors))];
    }
}
