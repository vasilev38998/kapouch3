<?php

declare(strict_types=1);

function config(string $key, mixed $default = null): mixed {
    static $cfg;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/../../config.php';
    }
    $parts = explode('.', $key);
    $value = $cfg;
    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function view(string $template, array $data = []): void {
    extract($data, EXTR_SKIP);
    $templatePath = __DIR__ . '/../views/' . $template . '.php';
    $layoutPath = __DIR__ . '/../views/layouts/main.php';
    require $layoutPath;
}

function redirect(string $path): never {
    header('Location: ' . $path);
    exit;
}

function method_is(string $method): bool {
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === strtoupper($method);
}

function now(): string {
    return date('Y-m-d H:i:s');
}

function app_log(string $message): void {
    $logFile = __DIR__ . '/../../storage/logs/app.log';
    $line = '[' . date('c') . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
}
