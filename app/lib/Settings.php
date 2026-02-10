<?php

declare(strict_types=1);

namespace App\Lib;

class Settings {
    public static function get(string $key, mixed $default = null): mixed {
        $stmt = Db::pdo()->prepare('SELECT value FROM settings WHERE `key` = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        if ($value === false) return $default;
        $json = json_decode((string)$value, true);
        return json_last_error() === JSON_ERROR_NONE ? $json : $value;
    }

    public static function set(string $key, mixed $value): void {
        $raw = is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE);
        $stmt = Db::pdo()->prepare('INSERT INTO settings(`key`,`value`,updated_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), updated_at=NOW()');
        $stmt->execute([$key, $raw]);
    }
}
