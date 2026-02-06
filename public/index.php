<?php

declare(strict_types=1);

require __DIR__ . '/../app/lib/helpers.php';

date_default_timezone_set(config('app.timezone', 'Europe/Moscow'));

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = __DIR__ . '/../app/' . strtolower(dirname($relative)) . '/' . basename($relative) . '.php';
    if (file_exists($file)) require $file;
});

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\ProfileController;
use App\Controllers\ReferralController;
use App\Controllers\StaffController;

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$routes = [
    'GET' => [
        '/' => [HomeController::class, 'index'],
        '/auth' => [AuthController::class, 'showPhone'],
        '/auth/verify' => [AuthController::class, 'showVerify'],
        '/logout' => [AuthController::class, 'logout'],
        '/profile' => [ProfileController::class, 'index'],
        '/profile/qr' => [ProfileController::class, 'qr'],
        '/profile/invite' => [ProfileController::class, 'invite'],
        '/profile/phone-change' => [ProfileController::class, 'phoneChange'],
        '/profile/birthday' => [ProfileController::class, 'birthday'],
        '/history' => [ProfileController::class, 'history'],
        '/staff' => [StaffController::class, 'dashboard'],
        '/staff/user/search' => [StaffController::class, 'userSearch'],
        '/staff/scan' => [StaffController::class, 'scan'],
        '/staff/order/create' => [StaffController::class, 'orderCreate'],
        '/staff/promocodes' => [StaffController::class, 'promocodes'],
        '/staff/missions' => [StaffController::class, 'missions'],
        '/admin/settings' => [AdminController::class, 'settings'],
        '/admin/users' => [AdminController::class, 'users'],
        '/admin/locations' => [AdminController::class, 'locations'],
        '/admin/promocodes' => [AdminController::class, 'promocodes'],
        '/admin/missions' => [AdminController::class, 'missions'],
        '/admin/exports' => [AdminController::class, 'exports'],
        '/admin/audit' => [AdminController::class, 'audit'],
    ],
    'POST' => [
        '/auth/send' => [AuthController::class, 'sendOtp'],
        '/auth/verify' => [AuthController::class, 'verifyOtp'],
        '/profile/phone-change' => [ProfileController::class, 'phoneChange'],
        '/profile/birthday' => [ProfileController::class, 'birthday'],
        '/staff/scan' => [StaffController::class, 'scan'],
        '/staff/order/create' => [StaffController::class, 'orderCreate'],
        '/staff/reward/redeem' => [StaffController::class, 'redeemReward'],
        '/admin/settings' => [AdminController::class, 'settings'],
        '/admin/users' => [AdminController::class, 'users'],
        '/admin/locations' => [AdminController::class, 'locations'],
        '/admin/promocodes' => [AdminController::class, 'promocodes'],
        '/admin/missions' => [AdminController::class, 'missions'],
    ],
];

if (preg_match('#^/r/([A-Za-z0-9_-]+)$#', $uri, $m)) {
    (new ReferralController())->capture($m[1]);
}
if (preg_match('#^/staff/order/(\d+)$#', $uri, $m) && $method === 'GET') {
    (new StaffController())->orderView((int)$m[1]);
    exit;
}
if (preg_match('#^/staff/order/(\d+)/reverse$#', $uri, $m) && $method === 'POST') {
    (new StaffController())->orderReverse((int)$m[1]);
    exit;
}

$action = $routes[$method][$uri] ?? null;
if (!$action) {
    http_response_code(404);
    echo '404';
    exit;
}
[$class, $fn] = $action;
(new $class())->$fn();
