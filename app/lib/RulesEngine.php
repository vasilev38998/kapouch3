<?php

declare(strict_types=1);

namespace App\Lib;

class RulesEngine {
    public function calculateOrder(array $orderInput): array {
        $total = (float)$orderInput['total_amount'];
        $weekday = date('D');
        $settings = [
            'cashback_percent' => (float)Settings::get('cashback_percent', 5),
            'stamps_required_for_reward' => (int)Settings::get('stamps_required_for_reward', 6),
            'stamps_rule_mode' => (string)Settings::get('stamps_rule_mode', 'per_order'),
            'stamps_per_order' => (int)Settings::get('stamps_per_order', 1),
            'stamps_per_amount_step' => (int)Settings::get('stamps_per_amount_step', 300),
            'double_stamps_days' => Settings::get('double_stamps_days', ['Fri', 'Sat']),
        ];

        $stamps = $settings['stamps_rule_mode'] === 'per_amount'
            ? (int)floor($total / max(1, $settings['stamps_per_amount_step']))
            : $settings['stamps_per_order'];

        if (in_array($weekday, (array)$settings['double_stamps_days'], true)) {
            $stamps *= 2;
        }

        $cashbackEarn = round(($total * $settings['cashback_percent']) / 100, 2);

        return [
            'stamps' => max(0, $stamps),
            'cashback_earn' => max(0, $cashbackEarn),
            'settings' => $settings,
        ];
    }

    public function cashbackMaxSpend(float $orderTotal): float {
        $percent = (float)Settings::get('cashback_max_spend_percent', 30);
        return round($orderTotal * $percent / 100, 2);
    }
}
