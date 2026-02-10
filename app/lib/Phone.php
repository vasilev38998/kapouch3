<?php

declare(strict_types=1);

namespace App\Lib;

class Phone {
    public static function normalize(string $input): ?string {
        $digits = preg_replace('/\D+/', '', $input);
        if (!$digits) return null;
        if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '7')) {
            return '+' . $digits;
        }
        return null;
    }
}
